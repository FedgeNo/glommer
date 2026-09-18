import { Api, ReadyHandler } from '/scripts/Runtime.js';
import { FormForm } from '/scripts/HTMLObjects.js';
import { CasinoWallet } from '/games/casino.js';

const TAU = Math.PI * 2;
const STORAGE_KEY = 'glommer.roulette.pending';

/** The animation is a presentation of a committed server result, never the draw. */
export class RouletteScene {
    constructor(element, pockets) {
        this.element = element;
        this.pockets = pockets;
        this.rotation = 0;
        this.ballAngle = 0;
        this.disposed = false;
        this.reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.ready = this.initialize().catch(() => this.fallback());
        window.addEventListener('pagehide', event => { if (!event.persisted) this.dispose(); });
    }

    async initialize() {
        const T = await import('https://cdn.jsdelivr.net/npm/three@0.180.0/build/three.module.js');
        if (this.disposed) return;
        this.T = T;
        this.renderer = new T.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' });
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = T.PCFSoftShadowMap;
        this.renderer.toneMapping = T.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = 1.15;
        this.scene = new T.Scene();
        this.camera = new T.PerspectiveCamera(39, 1, .1, 60);
        this.camera.position.set(0, 9.3, 6.7);
        this.camera.lookAt(0, 0, 0);

        // Softbox reflections give polished metal and lacquer something to reflect.
        const studio = new T.Scene();
        studio.background = new T.Color('#44483e');
        for (const [x, y, z, width, height] of [[-5, 7, 2, 5, 3], [5, 4, -4, 3, 6], [0, 8, -2, 6, 2]]) {
            const panel = new T.Mesh(new T.PlaneGeometry(width, height), new T.MeshBasicMaterial({ color: new T.Color(5, 4.5, 3.7), side: T.DoubleSide }));
            panel.position.set(x, y, z);
            panel.lookAt(0, 0, 0);
            studio.add(panel);
        }
        const pmrem = new T.PMREMGenerator(this.renderer);
        this.environment = pmrem.fromScene(studio, .08);
        this.scene.environment = this.environment.texture;
        pmrem.dispose();
        studio.traverse(item => { item.geometry?.dispose(); item.material?.dispose(); });
        this.scene.add(new T.HemisphereLight('#fff0d2', '#14241a', 2));
        const light = new T.DirectionalLight('#fff0d2', 3);
        light.position.set(-3, 8, 4);
        light.castShadow = true;
        light.shadow.mapSize.set(1024, 1024);
        Object.assign(light.shadow.camera, { left: -5, right: 5, top: 5, bottom: -5 });
        light.shadow.normalBias = .03;
        this.scene.add(light);

        const wood = new T.MeshPhysicalMaterial({ color: '#6c341b', map: this.texture('wood'), roughness: .27, clearcoat: 1, clearcoatRoughness: .2 });
        const brass = new T.MeshStandardMaterial({ color: '#d4ae60', metalness: .88, roughness: .25 });
        const dark = new T.MeshStandardMaterial({ color: '#121a16', roughness: .4, metalness: .25 });
        // Receive the wheel's real shadows over the room's shared green finish.
        const table = new T.ShadowMaterial({ color: '#061c14', opacity: .3 });
        this.mesh(new T.BoxGeometry(60, .25, 60), table, this.scene, 0, -.8, 0).castShadow = false;
        const profile = [[0, -.45], [3.15, -.45], [3.55, -.23], [3.65, .14], [3.62, .34], [3.52, .42], [3.4, .37], [3.3, .16], [3.17, .07], [2.82, -.05], [2.74, -.17], [0, -.17]];
        this.mesh(new T.LatheGeometry(profile.map(([x, y]) => new T.Vector2(x, y)), 160), wood);
        this.ring(3.54, .07, .34, brass);
        this.ring(3.23, .018, .08, brass);
        this.ring(2.8, .025, -.05, brass);
        // Fixed ivory deflectors along the ball track.
        for (let i = 0; i < 8; i++) {
            const a = i * TAU / 8;
            const pin = this.mesh(new T.BoxGeometry(.09, .05, .19), brass, this.scene, 3.03 * Math.cos(a), .045, -3.03 * Math.sin(a));
            pin.rotation.y = a + Math.PI / 4;
        }
        this.wheel = new T.Group();
        this.scene.add(this.wheel);
        this.mesh(new T.CylinderGeometry(2.72, 2.72, .12, 128), dark, this.wheel, 0, -.11, 0);
        const reds = new Set([1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36]);
        const colors = ['#136544', '#a21c27', '#101816'].map(color => new T.MeshStandardMaterial({ color, roughness: .38, metalness: .12 }));
        this.pockets.forEach((number, index) => {
            const angle = index * TAU / 37;
            const center = angle + Math.PI / 37;
            const material = colors[number === 0 ? 0 : reds.has(number) ? 1 : 2];
            for (const [inner, outer, y] of [[2.24, 2.69, -.035], [1.83, 2.21, -.02]]) {
                const sector = this.mesh(new T.RingGeometry(inner, outer, 8, 1, angle, TAU / 37), material, this.wheel, 0, y, 0);
                sector.rotation.x = -Math.PI / 2;
            }
            const divider = this.mesh(new T.BoxGeometry(.48, .095, .025), brass, this.wheel, 2.03 * Math.cos(angle), .02, -2.03 * Math.sin(angle));
            divider.rotation.y = angle;
            const label = document.createElement('canvas');
            label.width = 96; label.height = 128;
            const ctx = label.getContext('2d');
            ctx.fillStyle = '#fff2d5'; ctx.font = 'bold 66px Georgia'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            ctx.fillText(String(number), 48, 64);
            const map = new T.CanvasTexture(label);
            map.colorSpace = T.SRGBColorSpace;
            const plane = this.mesh(new T.PlaneGeometry(.24, .32), new T.MeshBasicMaterial({ map, transparent: true, depthWrite: false }), this.wheel, 2.47 * Math.cos(center), -.025, -2.47 * Math.sin(center));
            plane.rotation.set(-Math.PI / 2, 0, center - Math.PI / 2);
        });
        this.ring(2.72, .026, -.025, brass, this.wheel);
        this.ring(2.23, .025, .0, brass, this.wheel);
        this.ring(1.8, .04, .035, brass, this.wheel);
        this.mesh(new T.ConeGeometry(1.77, .55, 128), wood, this.wheel, 0, .23, 0);
        this.mesh(new T.CylinderGeometry(.14, .32, .72, 32), brass, this.wheel, 0, .74, 0);
        this.mesh(new T.SphereGeometry(.2, 24, 16), brass, this.wheel, 0, 1.17, 0);
        for (let i = 0; i < 4; i++) {
            const a = i * Math.PI / 2;
            const arm = this.mesh(new T.CylinderGeometry(.065, .065, 1.1, 16), brass, this.wheel, .53 * Math.cos(a), .82, -.53 * Math.sin(a));
            arm.rotation.z = Math.PI / 2;
            arm.rotation.y = a;
            this.mesh(new T.SphereGeometry(.09, 16, 12), brass, this.wheel, 1.07 * Math.cos(a), .82, -1.07 * Math.sin(a));
        }
        this.ball = this.mesh(new T.SphereGeometry(.09, 24, 16), new T.MeshPhysicalMaterial({ color: '#fffbe8', roughness: .16, clearcoat: 1 }), this.scene, 3.15, .19, 0);
        const canvas = this.renderer.domElement;
        canvas.setAttribute('aria-hidden', 'true');
        canvas.addEventListener('webglcontextlost', event => { event.preventDefault(); this.fallback(); });
        this.element.append(canvas);
        this.element.classList.add('is-3d');
        this.observer = new ResizeObserver(() => this.resize());
        this.observer.observe(this.element);
        if (this.settledNumber !== undefined) {
            this.wheel.rotation.y = this.rotation;
            this.ball.position.set(2.01 * Math.cos(this.ballAngle), .11, -2.01 * Math.sin(this.ballAngle));
        }
        this.resize();
    }

    texture(kind) {
        const T = this.T;
        const canvas = document.createElement('canvas'); canvas.width = canvas.height = 512;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = kind === 'wood' ? '#b88451' : '#bccbbb'; ctx.fillRect(0, 0, 512, 512);
        let seed = 71;
        const random = () => { seed = (seed * 1664525 + 1013904223) >>> 0; return seed / 4294967296; };
        for (let i = 0; i < (kind === 'wood' ? 850 : 24000); i++) {
            ctx.strokeStyle = `rgba(20,12,5,${random() * .13})`;
            ctx.fillStyle = `rgba(20,30,20,${random() * .2})`;
            if (kind === 'felt') ctx.fillRect(random() * 512, random() * 512, 1, 2);
            else {
                const y = random() * 512; ctx.beginPath(); ctx.moveTo(0, y);
                ctx.bezierCurveTo(160, y + random() * 30, 300, y - random() * 30, 512, y + 8); ctx.stroke();
            }
        }
        const map = new T.CanvasTexture(canvas); map.colorSpace = T.SRGBColorSpace;
        map.wrapS = map.wrapT = T.RepeatWrapping;
        map.repeat.set(kind === 'felt' ? 5 : 2, kind === 'felt' ? 5 : 2);
        return map;
    }

    mesh(geometry, material, parent = this.scene, x = 0, y = 0, z = 0) {
        const mesh = new this.T.Mesh(geometry, material);
        mesh.position.set(x, y, z); mesh.castShadow = true; mesh.receiveShadow = true; parent.add(mesh); return mesh;
    }

    ring(radius, tube, y, material, parent = this.scene) {
        const ring = this.mesh(new this.T.TorusGeometry(radius, tube, 12, 128), material, parent, 0, y, 0);
        ring.rotation.x = -Math.PI / 2;
    }

    resize() {
        if (!this.renderer || this.failed || this.disposed) return;
        const { width, height } = this.element.getBoundingClientRect();
        if (!width || !height) return;
        this.renderer.setSize(width, height, false);
        this.camera.aspect = width / height;
        this.camera.position.set(0, 9.3, 6.7).multiplyScalar(Math.max(1, 1 / this.camera.aspect));
        this.camera.updateProjectionMatrix();
        this.render();
    }

    render() { if (!this.failed && !this.disposed) this.renderer?.render(this.scene, this.camera); }
    fallback() {
        this.failed = true; this.element.classList.remove('is-3d'); this.renderer?.domElement.remove();
        this.element.querySelector('.wheel-fallback').hidden = false;
    }

    static pocketAngle(pockets, number, rotation) {
        const index = pockets.indexOf(number);
        if (index < 0) throw new Error('Unknown roulette result.');
        return rotation + (index + .5) * TAU / pockets.length;
    }

    async spin(number, signal) {
        const target = this.rotation + TAU * 3 + 1.1;
        const angle = RouletteScene.pocketAngle(this.pockets, number, target);
        const ballTarget = angle - Math.ceil((angle - this.ballAngle) / TAU) * TAU - 5 * TAU;
        const initial = this.rotation, ballInitial = this.ballAngle;
        const duration = this.reduced.matches || this.failed || !this.ball ? 0 : 6500;
        const start = performance.now();
        await new Promise(resolve => {
            let timer;
            const finish = () => { clearTimeout(timer); signal?.removeEventListener('abort', finish); resolve(); };
            signal?.addEventListener('abort', finish, { once: true });
            const frame = () => {
                if (signal?.aborted || this.disposed) return finish();
                const t = duration ? Math.min(1, (performance.now() - start) / duration) : 1;
                const ease = 1 - (1 - t) ** 3;
                this.rotation = initial + (target - initial) * ease;
                this.ballAngle = ballInitial + (ballTarget - ballInitial) * (1 - (1 - t) ** 2);
                const drop = Math.max(0, Math.min(1, (t - .55) / .35));
                const radius = 3.14 - 1.13 * drop;
                if (this.ball) {
                    this.wheel.rotation.y = this.rotation;
                    this.ball.position.set(radius * Math.cos(this.ballAngle), .18 - .07 * drop + Math.abs(Math.sin(t * 85)) * .1 * drop * (1 - drop), -radius * Math.sin(this.ballAngle));
                }
                if (!document.hidden) this.render();
                if (t === 1) return finish();
                timer = setTimeout(frame, document.hidden ? 250 : 1000 / 40);
            };
            frame();
        });
        if (signal?.aborted || this.disposed) return;
        this.settledNumber = number;
        this.element.querySelector('.wheel-fallback').textContent = String(number);
    }

    dispose() {
        this.disposed = true; this.observer?.disconnect();
        const materials = new Set(), textures = new Set();
        this.scene?.traverse(item => { item.geometry?.dispose(); if (item.material) materials.add(item.material); });
        materials.forEach(material => { if (material.map) textures.add(material.map); material.dispose(); });
        textures.forEach(texture => texture.dispose());
        this.environment?.dispose(); this.renderer?.dispose();
    }
}

export class RouletteTable {
    constructor(root, { wallet = new CasinoWallet(root), scene = new RouletteScene(root.querySelector('[data-scene]'), JSON.parse(root.dataset.wheel)), storage = null } = {}) {
        this.root = root; this.wallet = wallet; this.scene = scene;
        try { this.storage = storage || window.sessionStorage; } catch { this.storage = null; }
        this.positions = JSON.parse(root.dataset.positions);
        this.form = root.querySelector('[data-bet-form]');
        this.result = root.querySelector('[data-result]');
        this.bets = {}; this.chip = 25; this.pending = null; this.busy = false;
        try {
            const saved = JSON.parse(this.storage.getItem(STORAGE_KEY));
            if (saved && /^[a-f0-9]{32}$/.test(saved.requestKey) && this.validBets(saved.bets)) {
                this.pending = saved; this.bets = { ...saved.bets };
                this.say('A spin needs confirmation. Press Recover spin to retrieve its result.');
            }
        } catch { /* Storage may be unavailable; submission explains that before wagering. */ }
        this.form.addEventListener('click', event => {
            const button = event.target.closest('button');
            if (!button || this.busy || this.pending) return;
            if (button.dataset.chip) {
                this.chip = Number(button.dataset.chip);
                this.form.querySelectorAll('[data-chip]').forEach(chip => chip.setAttribute('aria-pressed', String(chip === button)));
            } else if (button.dataset.bet) this.add(button.dataset.bet);
            else if (button.hasAttribute('data-add-inside')) this.add(this.form.querySelector('#inside-position').value);
            else if (button.dataset.remove) { delete this.bets[button.dataset.remove]; this.render(); }
            else if (button.hasAttribute('data-clear')) { this.bets = {}; this.render(); }
        });
        FormForm.attach(this.form, (_, { signal }) => this.spin(signal), { settled: () => this.render() });
        this.wallet.listeners.add(data => { if (data.history) this.history(data.history); this.render(); });
        window.addEventListener('pagehide', () => FormForm.cancel(this.form));
        window.addEventListener('pageshow', () => this.render());
        this.render();
    }

    validBets(bets) {
        return bets && typeof bets === 'object' && !Array.isArray(bets) && Object.keys(bets).length > 0
            && Object.entries(bets).every(([key, stake]) => Object.hasOwn(this.positions, key) && Number.isSafeInteger(stake) && stake > 0)
            && Object.values(bets).reduce((a, b) => a + b, 0) <= 10000;
    }
    total() { return Object.values(this.bets).reduce((sum, stake) => sum + stake, 0); }
    say(text) { this.result.textContent = text; }
    add(position) {
        if (!Object.hasOwn(this.positions, position)) return;
        if (!this.wallet.data) return this.say('Connecting to your chips. Please wait.');
        if (this.total() + this.chip > Math.min(10000, this.wallet.data.balance)) return this.say('That chip exceeds your balance or the 10,000-chip table limit.');
        this.bets[position] = (this.bets[position] || 0) + this.chip;
        this.render();
    }

    render() {
        const locked = this.busy || !!this.pending;
        this.form.querySelectorAll('button:not([data-spin]), select').forEach(control => { control.disabled = locked; });
        const spin = this.form.querySelector('[data-spin]');
        spin.disabled = this.busy || (!this.pending && (!this.total() || !this.wallet.data || this.total() > this.wallet.data.balance));
        spin.textContent = this.busy ? 'SPINNING…' : this.pending ? 'RECOVER SPIN' : 'SPIN THE WHEEL';
        this.form.querySelector('[data-total]').textContent = `Total: ${this.total().toLocaleString()} chips`;
        this.form.querySelectorAll('[data-bet]').forEach(button => {
            const stake = this.bets[button.dataset.bet];
            if (stake) button.dataset.stake = stake; else delete button.dataset.stake;
            const rule = this.positions[button.dataset.bet];
            button.setAttribute('aria-label', `${rule.label}, pays ${rule.odds} to 1${stake ? `, ${stake} chips placed` : ''}`);
        });
        const slip = this.form.querySelector('[data-slip]'); slip.replaceChildren();
        Object.entries(this.bets).forEach(([key, amount]) => {
            const item = document.createElement('li'), button = document.createElement('button');
            button.type = 'button'; button.dataset.remove = key; button.disabled = locked;
            button.textContent = `${this.positions[key].label}: ${amount.toLocaleString()} ×`;
            button.setAttribute('aria-label', `Remove ${amount} chips from ${this.positions[key].label}`);
            item.append(button); slip.append(item);
        });
    }

    history(rounds) {
        const history = this.root.querySelector('[data-history]'); history.replaceChildren();
        rounds.slice(0, 12).forEach(round => {
            const item = document.createElement('span');
            item.className = ['red', 'black', 'green'].includes(round.color) ? round.color : '';
            item.textContent = String(round.number); item.title = `${round.number}, ${round.color}`;
            history.append(item);
        });
        this.rounds = rounds;
    }

    async spin(signal) {
        if (this.busy) return;
        if (!this.pending) {
            if (!this.validBets(this.bets) || !this.wallet.data || this.total() > this.wallet.data.balance) return;
            const bytes = new Uint8Array(16); window.crypto.getRandomValues(bytes);
            const requestKey = Array.from(bytes, n => n.toString(16).padStart(2, '0')).join('');
            const request = { requestKey, bets: { ...this.bets } };
            try { this.storage.setItem(STORAGE_KEY, JSON.stringify(request)); }
            catch { this.say('Enable session storage in your browser so interrupted spins can be recovered safely.'); return; }
            this.pending = request;
        }
        this.busy = true; this.wallet.pause(); this.render();
        this.say('No more bets. The wheel is turning…');
        const controller = new AbortController();
        const abort = () => controller.abort();
        signal?.addEventListener('abort', abort, { once: true });
        const timeout = setTimeout(abort, 20000);
        try {
            if (signal?.aborted) return;
            const response = await Api.request('/api/roulette', this.pending, { signal: controller.signal });
            if (signal?.aborted) return;
            if (!response.ok) {
                // Only explicit endpoint rejections prove the bet did not commit.
                if ([409, 422].includes(response.status) && response.data?.error) this.clearPending();
                this.say(this.pending ? 'Connection interrupted. Press Recover spin; your bet will not be charged twice.' : response.error || 'That bet could not be placed.');
                return;
            }
            const { wallet, round } = response.data.response || {};
            if (!round || round.requestKey !== this.pending.requestKey || !Number.isInteger(round.number) || round.number < 0 || round.number > 36 || !wallet) throw new Error('Incomplete spin response');
            clearTimeout(timeout);
            this.root.querySelector('.roulette-stage').scrollIntoView?.({ behavior: this.scene.reduced?.matches ? 'instant' : 'smooth', block: 'nearest' });
            await this.scene.spin(round.number, signal);
            if (signal?.aborted) return;
            this.wallet.update(wallet);
            this.history([round, ...(this.rounds || []).filter(item => item.requestKey !== round.requestKey)]);
            this.say(`${round.number} · ${round.color.toUpperCase()} — ${round.net > 0 ? `Won ${round.net.toLocaleString()} chips` : round.net === 0 ? 'Bets returned' : `Lost ${(-round.net).toLocaleString()} chips`}.`);
            this.clearPending();
        } catch {
            if (!signal?.aborted) this.say('Your spin needs confirmation. Press Recover spin to retrieve its result.');
        } finally {
            clearTimeout(timeout); signal?.removeEventListener('abort', abort);
            this.busy = false;
            if (!signal?.aborted) { this.wallet.resume(); this.render(); }
        }
    }

    clearPending() {
        this.storage.removeItem(STORAGE_KEY);
        this.pending = null;
    }
}

ReadyHandler.add(() => {
    const root = document.querySelector('[data-roulette]');
    if (!root) return;
    const game = new RouletteTable(root);
    game.wallet.start();
});
