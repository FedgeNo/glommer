import { Api, ReadyHandler } from '/scripts/Runtime.js';
import { FormForm } from '/scripts/HTMLObjects.js';
import { CasinoWallet } from '/games/casino.js';

const COLORS = ['#76f9ff', '#b895ff', '#66ffb9', '#ffca73', '#ff82dc', '#fff0a0'];
const LINE_COLORS = [...COLORS, '#ff987e', '#aaff70', '#7daaff', '#e6dfff'];
function node(tag, className, text) {
    const result = document.createElement(tag);
    if (className) result.className = className;
    if (text !== undefined) result.textContent = text;
    return result;
}
function svg(tag, attributes = {}) {
    const result = document.createElementNS('http://www.w3.org/2000/svg', tag);
    for (const [key, value] of Object.entries(attributes)) result.setAttribute(key, String(value));
    return result;
}

export class VLTSymbol {
    static sequence = 0;
    constructor(symbol, rules) { this.symbol = symbol; this.rules = rules; }
    toDOM() {
        const root = node('span', 'VLTSymbol Symbol' + this.symbol);
        root.setAttribute('role', 'img'); root.setAttribute('aria-label', this.rules[this.symbol].name);
        const art = svg('svg', { viewBox: '0 0 100 100', 'aria-hidden': 'true' });
        const id = 'vlt-jewel-' + (++VLTSymbol.sequence);
        const gradient = svg('linearGradient', { id, x1: '0', y1: '0', x2: '1', y2: '1' });
        gradient.append(svg('stop', { offset: '0', 'stop-color': '#fff' }), svg('stop', { offset: '.4', 'stop-color': COLORS[this.symbol] }), svg('stop', { offset: '1', 'stop-color': '#5b245f' }));
        const defs = svg('defs'); defs.append(gradient); art.append(defs);
        const group = svg('g', { fill: `url(#${id})`, stroke: COLORS[this.symbol], 'stroke-width': 2, 'stroke-linejoin': 'round' });
        if (this.symbol === 0) group.append(svg('path', { d: 'M59 8 21 55 46 55 36 92 80 39 55 39Z' }));
        if (this.symbol === 1) {
            group.append(svg('circle', { cx: 50, cy: 50, r: 24 }));
            group.append(svg('ellipse', { cx: 50, cy: 50, rx: 43, ry: 13, fill: 'none', transform: 'rotate(-32 50 50)', 'stroke-width': 5 }));
            group.append(svg('circle', { cx: 74, cy: 18, r: 5, fill: '#fff' }));
        }
        if (this.symbol === 2) {
            group.append(svg('path', { d: 'M27 17 73 17 92 40 50 90 8 40Z' }));
            group.append(svg('path', { d: 'M8 40H92M27 17 34 40 50 90 66 40 73 17M34 40 50 17 66 40', fill: 'none', stroke: '#e3fff1', 'stroke-width': 1.5 }));
        }
        if (this.symbol === 3) group.append(svg('path', { d: 'M50 5 63 34 96 38 71 60 78 94 50 77 22 94 29 60 4 38 37 34Z' }));
        if (this.symbol === 4) {
            group.append(svg('path', { d: 'M16 70 8 28 33 46 50 12 67 46 92 28 84 70ZM19 78H81V89H19Z' }));
            for (const x of [30, 50, 70]) group.append(svg('circle', { cx: x, cy: 62, r: 4, fill: '#fff1b7' }));
        }
        if (this.symbol === 5) {
            const seven = svg('text', { x: 48, y: 85, 'text-anchor': 'middle', 'font-size': 98, 'font-weight': 950, 'font-family': 'Georgia, serif', transform: 'skewX(-5)' });
            seven.textContent = '7'; group.append(seven);
            group.append(svg('path', { d: 'M78 6 81 16 91 19 81 22 78 32 75 22 65 19 75 16Z', fill: '#fff' }));
        }
        art.append(group); root.append(art); return root;
    }
}

export class VLTAudio {
    constructor(button) {
        this.button = button; this.enabled = false;
        try { this.enabled = localStorage.getItem('vltSound') === 'on'; } catch {}
        this.label();
        button.addEventListener('click', () => {
            this.enabled = !this.enabled;
            try { localStorage.setItem('vltSound', this.enabled ? 'on' : 'off'); } catch {}
            if (!this.enabled) this.stop();
            this.label(); this.unlock(); this.play('stop');
        });
    }
    label() { this.button.textContent = this.enabled ? 'Mute Sound' : 'Enable Sound'; this.button.setAttribute('aria-pressed', String(this.enabled)); }
    unlock() {
        if (!this.enabled) return;
        try { const Audio = window.AudioContext || window.webkitAudioContext; if (Audio) { this.context ||= new Audio(); this.context.resume().catch(() => {}); } } catch {}
    }
    play(kind, level = 1) {
        if (!this.enabled || document.hidden || this.context?.state !== 'running') return;
        const notes = kind === 'win' ? [261.63, 329.63, 392, 523.25, 659.25, 783.99, 1046.5].slice(0, level >= 20 ? 7 : 4)
            : kind === 'spin' ? [130.8, 196, 261.6, 392] : [220 + level * 100];
        notes.forEach((frequency, index) => {
            const oscillator = this.context.createOscillator(), gain = this.context.createGain();
            const start = this.context.currentTime + index * .09;
            oscillator.type = 'triangle'; oscillator.frequency.setValueAtTime(frequency, start);
            gain.gain.setValueAtTime(.0001, start); gain.gain.exponentialRampToValueAtTime(.035, start + .015);
            gain.gain.exponentialRampToValueAtTime(.0001, start + .24);
            oscillator.connect(gain); gain.connect(this.context.destination);
            oscillator.start(start); oscillator.stop(start + .25);
            oscillator.onended = () => { oscillator.disconnect(); gain.disconnect(); };
        });
    }
    stop() { this.context?.close().catch(() => {}); this.context = null; }
}

/** A procedural cosmic cabinet, with no downloaded textures or image assets. */
export class VLTScene {
    constructor(root) {
        this.root = root; this.disposed = false; this.energy = 0; this.frame = null;
        this.reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.visibility = () => { window.cancelAnimationFrame(this.frame); if (!document.hidden && !this.disposed) this.loop(performance.now()); };
        document.addEventListener('visibilitychange', this.visibility);
        this.reduced.addEventListener('change', this.visibility);
        this.ready = this.initialize().catch(() => this.dispose());
    }
    async initialize() {
        const T = await import('https://cdn.jsdelivr.net/npm/three@0.180.0/build/three.module.js');
        if (this.disposed) return;
        this.T = T;
        this.renderer = new T.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' });
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
        this.renderer.toneMapping = T.ACESFilmicToneMapping;
        const glow = document.createElement('canvas'); glow.width = glow.height = 64;
        const context = glow.getContext('2d');
        const gradient = context.createRadialGradient(32, 32, 0, 32, 32, 32);
        gradient.addColorStop(0, '#ffffffff'); gradient.addColorStop(.15, '#ffffffaa');
        gradient.addColorStop(.45, '#ffffff33'); gradient.addColorStop(1, '#ffffff00');
        context.fillStyle = gradient; context.fillRect(0, 0, 64, 64);
        this.glowTexture = new T.CanvasTexture(glow);
        this.scene = new T.Scene();
        this.camera = new T.PerspectiveCamera(48, 1, .1, 100); this.camera.position.z = 9;
        this.scene.add(new T.AmbientLight('#ad82ff', 2));
        const light = new T.PointLight('#7aefff', 70); light.position.set(-3, 3, 4); this.scene.add(light);
        const warm = new T.PointLight('#ff7add', 90); warm.position.set(4, -1, 3); this.scene.add(warm);
        this.rings = new T.Group(); this.scene.add(this.rings);
        for (let index = 0; index < 5; index++) {
            const ring = new T.Mesh(new T.TorusGeometry(2.6 + index * .25, .015 + index * .008, 8, 100),
                new T.MeshBasicMaterial({ color: index % 2 ? '#e780ff' : '#66eaff', transparent: true, opacity: .45 + index * .07, blending: T.AdditiveBlending, depthWrite: false, toneMapped: false }));
            for (const [spread, opacity] of [[2.5, .12], [5, .055], [9, .025]]) {
                ring.add(new T.Mesh(new T.TorusGeometry(2.6 + index * .25, (.015 + index * .008) * spread, 8, 100),
                    new T.MeshBasicMaterial({ color: ring.material.color, transparent: true, opacity, blending: T.AdditiveBlending, depthWrite: false, toneMapped: false })));
            }
            ring.rotation.set(.25 + index * .17, index * .22, index * .4); this.rings.add(ring);
        }
        this.gems = new T.Group(); this.scene.add(this.gems);
        for (let index = 0; index < 12; index++) {
            const gem = new T.Mesh(new T.OctahedronGeometry(.12 + index % 3 * .06), new T.MeshStandardMaterial({ color: COLORS[index % 6], metalness: .65, roughness: .15, emissive: COLORS[index % 6], emissiveIntensity: .6, transparent: true, opacity: .9, blending: T.AdditiveBlending, depthWrite: false }));
            gem.add(this.halo(COLORS[index % 6], .8 + index % 3 * .25, .5));
            const angle = index / 12 * Math.PI * 2;
            gem.position.set(Math.cos(angle) * 4.2, Math.sin(angle) * 3.3, 0); this.gems.add(gem);
        }
        const positions = new Float32Array(750 * 3);
        for (let index = 0; index < positions.length; index++) positions[index] = (Math.random() - .5) * 22;
        const geometry = new T.BufferGeometry(); geometry.setAttribute('position', new T.BufferAttribute(positions, 3));
        this.stars = new T.Points(geometry, new T.PointsMaterial({ color: '#c6c2ff', map: this.glowTexture, size: .12, transparent: true, opacity: .9, blending: T.AdditiveBlending, depthWrite: false, toneMapped: false }));
        this.scene.add(this.stars);
        this.coins = new T.Group(); this.scene.add(this.coins);
        const coinGeometry = new T.CylinderGeometry(.09, .09, .025, 16);
        const coinMaterial = new T.MeshStandardMaterial({ color: '#ffd36d', metalness: .8, roughness: .23, emissive: '#ffb342', emissiveIntensity: .4, transparent: true, opacity: .95, blending: T.AdditiveBlending, depthWrite: false });
        for (let index = 0; index < 48; index++) {
            const coin = new T.Mesh(coinGeometry, coinMaterial);
            coin.add(this.halo('#ffd36d', .55, .4));
            coin.visible = false; this.coins.add(coin);
        }
        this.root.append(this.renderer.domElement);
        this.resize = new ResizeObserver(() => {
            this.camera.aspect = (this.root.clientWidth || 900) / (this.root.clientHeight || 600);
            this.camera.updateProjectionMatrix(); this.renderer.setSize(this.root.clientWidth || 900, this.root.clientHeight || 600, false); this.draw();
        });
        this.resize.observe(this.root); this.loop(performance.now());
    }
    halo(color, size, opacity) {
        const sprite = new this.T.Sprite(new this.T.SpriteMaterial({ map: this.glowTexture, color, opacity, transparent: true,
            blending: this.T.AdditiveBlending, depthWrite: false, toneMapped: false }));
        sprite.scale.set(size, size, 1);
        return sprite;
    }
    excite(amount) { this.energy = amount; if (this.reduced.matches) this.draw(); }
    celebrate(ratio) {
        this.excite(Math.min(5, 1 + ratio / 10));
        if (!this.coins || this.reduced.matches) return;
        this.burstAt = performance.now();
        this.coins.children.forEach((coin, index) => {
            coin.visible = index < (ratio >= 20 ? 48 : ratio >= 5 ? 32 : 16);
            coin.userData.velocity = { x: (Math.random() - .5) * 7, y: 3 + Math.random() * 5, z: Math.random() * 2 };
        });
    }
    draw() { if (this.renderer && !this.disposed && !document.hidden) this.renderer.render(this.scene, this.camera); }
    loop(now) {
        if (this.disposed || !this.renderer || document.hidden) return;
        if (now - (this.lastFrame || 0) >= 32) {
            const delta = Math.min(.1, (now - (this.lastFrame || now)) / 1000); this.lastFrame = now;
            if (!this.reduced.matches) {
                this.rings.rotation.z += delta * (.08 + this.energy * .3);
                this.rings.children.forEach((ring, index) => { ring.rotation.y += delta * .09 * (index % 2 ? 1 : -1); });
                this.gems.rotation.z -= delta * .05; this.gems.children.forEach(gem => { gem.rotation.y += delta * (.3 + this.energy); });
                this.stars.rotation.z += delta * (.012 + this.energy * .04);
                this.stars.position.z = Math.sin(now / 1200) * this.energy * .35;
                this.energy *= Math.exp(-delta * .6);
                if (this.burstAt) {
                    const age = (now - this.burstAt) / 1000;
                    this.coins.children.forEach((coin, index) => {
                        if (!coin.visible) return;
                        coin.visible = age < 2.5;
                        coin.position.set(coin.userData.velocity.x * age, -1 + coin.userData.velocity.y * age - 4 * age * age, coin.userData.velocity.z);
                        coin.rotation.set(age * 7 + index, age * 4, age * 2);
                    });
                }
            }
            this.draw();
        }
        if (!this.reduced.matches) this.frame = window.requestAnimationFrame(time => this.loop(time));
    }
    dispose() {
        this.disposed = true; window.cancelAnimationFrame(this.frame); this.resize?.disconnect();
        document.removeEventListener('visibilitychange', this.visibility); this.reduced.removeEventListener('change', this.visibility);
        this.scene?.traverse(item => { item.geometry?.dispose(); item.material?.dispose(); });
        this.glowTexture?.dispose();
        this.renderer?.dispose(); this.renderer?.domElement.remove();
    }
}

export class VLTReels {
    constructor(root, symbols, audio, SymbolType = VLTSymbol) { this.root = root; this.symbols = symbols; this.audio = audio; this.SymbolType = SymbolType; this.reduced = window.matchMedia('(prefers-reduced-motion: reduce)'); }
    symbol(value) { return new this.SymbolType(value, this.symbols).toDOM(); }
    display(grid) {
        this.root.querySelectorAll('.VLTReel').forEach((column, reel) => column.replaceChildren(...grid[reel].map(symbol => this.symbol(symbol))));
    }
    async spin(grid, signal) {
        if (signal?.aborted) return;
        if (this.reduced.matches) { this.display(grid); return; }
        await Promise.all(Array.from(this.root.querySelectorAll('.VLTReel'), async (column, reel) => {
            const strip = node('div', 'VLTStrip');
            const count = 16 + reel * 5;
            for (let index = 0; index < count; index++) strip.append(this.symbol(Math.floor(Math.random() * this.symbols.length)));
            grid[reel].forEach(symbol => strip.append(this.symbol(symbol)));
            column.replaceChildren(strip);
            const height = column.clientHeight / 3;
            if (!strip.animate || !height) { column.replaceChildren(...grid[reel].map(symbol => this.symbol(symbol))); return; }
            const animation = strip.animate([{ transform: 'translateY(0)' }, { transform: `translateY(${-count * height}px)` }],
                { duration: 1500 + reel * 330, easing: 'cubic-bezier(.15,.65,.18,1)', fill: 'forwards' });
            const abort = () => animation.cancel(); signal?.addEventListener('abort', abort, { once: true });
            try { await animation.finished; } catch {} finally { signal?.removeEventListener('abort', abort); }
            if (signal?.aborted) return;
            column.replaceChildren(...grid[reel].map(symbol => this.symbol(symbol)));
            animation.cancel(); this.audio.play('stop', reel + 1);
        }));
    }
}

export class VLTMachine {
    constructor(root, { wallet = new CasinoWallet(root), scene = null, reels = null, storage = null, SymbolType = VLTSymbol, AudioType = VLTAudio } = {}) {
        this.root = root; this.wallet = wallet; this.form = root.querySelector('.VLTForm');
        this.game = root.dataset.vltGame || 'vlt';
        this.presentation = JSON.parse(root.dataset.vltPresentation);
        this.storageKey = `glommer.${this.game}.pending`;
        this.symbols = JSON.parse(root.dataset.symbols); this.lines = JSON.parse(root.dataset.lines);
        this.audio = new AudioType(root.querySelector('.VLTSound'));
        this.scene = scene || new VLTScene(root.querySelector('.VLTScene'));
        this.reels = reels || new VLTReels(root, this.symbols, this.audio, SymbolType);
        this.busy = false; this.pending = null; this.countFrame = null;
        try { this.storage = storage || window.sessionStorage; } catch { this.storage = null; }
        try {
            const saved = JSON.parse(this.storage.getItem(this.storageKey));
            if (saved && /^[a-f0-9]{32}$/.test(saved.requestKey) && this.validStake(saved.stake)) {
                this.pending = { requestKey: saved.requestKey, stake: saved.stake }; this.form.elements.stake.value = String(saved.stake);
                this.say('A spin needs confirmation. Recover it to retrieve the same result.');
            }
        } catch {}
        this.reels.display(Array.from({ length: 5 }, (_, reel) => Array.from({ length: Number(root.dataset.rows) || 3 }, (_, row) => (reel + row) % 6)));
        this.guide();
        FormForm.attach(this.form, (_, { signal }) => this.spin(signal), { settled: () => this.controls() });
        this.form.addEventListener('click', () => this.audio.unlock());
        this.form.elements.stake.addEventListener('change', () => this.controls());
        this.wallet.listeners.add(() => this.controls());
        window.addEventListener('pagehide', event => { FormForm.cancel(this.form); window.cancelAnimationFrame(this.countFrame); this.audio.stop(); if (!event.persisted) this.scene.dispose(); });
        window.addEventListener('pageshow', () => this.controls());
        this.controls();
    }
    validStake(stake) { return Number.isSafeInteger(stake) && Array.from(this.form.elements.stake.options).some(option => Number(option.value) === stake); }
    say(text) { this.root.querySelector('.VLTResult').textContent = text; }
    controls() {
        const stake = Number(this.form.elements.stake.value);
        this.form.elements.stake.disabled = this.busy || Boolean(this.pending);
        const button = this.root.querySelector('.VLTSpin');
        button.disabled = this.busy || !this.pending && (!this.wallet.data || !this.validStake(stake) || stake > this.wallet.data.balance);
        button.textContent = this.busy ? this.presentation.pending : this.pending ? 'RECOVER SPIN' : this.presentation.spin;
    }
    path(line, className) {
        const picture = svg('svg', { viewBox: '0 0 500 300', preserveAspectRatio: 'none', 'aria-hidden': 'true' });
        if (className) picture.setAttribute('class', className);
        picture.append(svg('polyline', { points: this.lines[line].map((row, reel) => `${50 + reel * 100},${50 + row * 100}`).join(' ') }));
        return picture;
    }
    guide() {
        const guide = this.root.querySelector('.VLTLineGuide'); guide.replaceChildren();
        this.root.querySelector('.VLTPaylines')?.remove();
        const overlay = svg('svg', { viewBox: '0 0 500 300', preserveAspectRatio: 'none', class: 'VLTPaylines', 'aria-hidden': 'true' });
        this.lines.forEach((rows, line) => {
            const figure = node('figure'); const picture = this.path(line);
            const path = picture.querySelector('polyline').cloneNode(true);
            path.style.stroke = LINE_COLORS[line]; overlay.append(path);
            picture.querySelector('polyline').style.stroke = LINE_COLORS[line];
            for (let reel = 0; reel < 5; reel++) for (let row = 0; row < 3; row++) picture.prepend(svg('circle', { cx: 50 + reel * 100, cy: 50 + row * 100, r: 8 }));
            figure.append(picture, node('figcaption', '', `Line ${line + 1} · ${rows.map(row => ['top', 'middle', 'bottom'][row]).join(', ')}`)); guide.append(figure);
        });
        this.root.querySelector('.VLTWindow').append(overlay);
    }
    highlight(round, selected = null) {
        this.root.querySelectorAll('.VLTPaths').forEach(path => path.remove());
        this.root.querySelectorAll('.VLTSymbol').forEach(symbol => symbol.classList.remove('Winning'));
        const wins = selected === null ? round.wins : round.wins.filter(win => win.line === selected);
        this.root.querySelectorAll('.VLTPaylines polyline').forEach((path, line) => path.classList.toggle('Matching', wins.some(win => win.line === line)));
        wins.forEach(win => {
            for (let reel = 0; reel < win.count; reel++) this.root.querySelectorAll('.VLTReel')[reel].children[this.lines[win.line][reel]].classList.add('Winning');
            if (selected !== null) this.root.querySelector('.VLTWindow').append(this.path(win.line, 'VLTPaths'));
        });
        this.root.querySelectorAll('.VLTWins button').forEach((button, index) => button.setAttribute('aria-pressed', String(round.wins[index].line === selected)));
    }
    result(round) {
        this.round = round;
        const ratio = round.payout / round.wager;
        const title = round.net > 0 ? ratio >= 20 ? this.presentation.hugeWin : ratio >= 5 ? this.presentation.bigWin : this.presentation.win : round.net === 0 ? 'STAKE RETURNED' : 'SPIN COMPLETE';
        this.root.querySelector('.VLTWinTitle').textContent = title;
        this.root.querySelector('.VLTCabinet').classList.toggle('Celebrating', round.net > 0);
        this.root.querySelector('.VLTCabinet').classList.toggle('SuperWin', round.net > 0 && ratio >= 20);
        this.say(`${round.payout.toLocaleString()} chips returned · ${round.wager.toLocaleString()} wagered · ${round.net > 0 ? '+' : round.net < 0 ? '−' : ''}${Math.abs(round.net).toLocaleString()} net.`);
        this.renderWins(round);
        window.cancelAnimationFrame(this.countFrame);
        const amount = this.root.querySelector('.VLTWinAmount');
        const duration = round.net > 0 && !this.reels.reduced?.matches ? Math.min(2300, 600 + ratio * 50) : 0;
        const start = performance.now();
        const count = now => {
            const progress = duration ? Math.min(1, (now - start) / duration) : 1;
            amount.textContent = Math.round(round.payout * (1 - (1 - progress) ** 3)).toLocaleString();
            if (progress < 1) this.countFrame = window.requestAnimationFrame(count);
        };
        count(start);
        if (round.net > 0) { this.scene.celebrate(ratio); this.audio.play('win', ratio); }
    }
    async spin(signal) {
        if (this.busy || signal?.aborted) return;
        if (!this.pending) {
            const stake = Number(this.form.elements.stake.value);
            if (!this.validStake(stake) || !this.wallet.data || stake > this.wallet.data.balance) return;
            const requestKey = Array.from(crypto.getRandomValues(new Uint8Array(16)), byte => byte.toString(16).padStart(2, '0')).join('');
            const request = { stake, requestKey };
            try { this.storage.setItem(this.storageKey, JSON.stringify(request)); }
            catch { this.say('Enable session storage so interrupted spins can be recovered safely.'); return; }
            this.pending = request;
        }
        this.busy = true; this.wallet.pause(); this.controls();
        window.cancelAnimationFrame(this.countFrame);
        this.root.querySelector('.VLTWinAmount').textContent = '0';
        this.root.querySelector('.VLTWinTitle').textContent = this.presentation.charging;
        this.root.querySelector('.VLTCabinet').classList.remove('Celebrating', 'SuperWin');
        this.root.querySelector('.VLTWins').replaceChildren();
        this.root.querySelectorAll('.VLTPaths').forEach(path => path.remove());
        this.root.querySelectorAll('.Winning').forEach(symbol => symbol.classList.remove('Winning'));
        this.root.querySelectorAll('.VLTPaylines .Matching').forEach(path => path.classList.remove('Matching'));
        this.say(this.presentation.contact);
        const controller = new AbortController(); const abort = () => controller.abort();
        signal?.addEventListener('abort', abort, { once: true });
        const timeout = setTimeout(abort, 15000);
        try {
            const response = await Api.request('/api/' + this.game, this.pending, { signal: controller.signal });
            if (signal?.aborted) return;
            if (controller.signal.aborted) throw new Error('Request timed out');
            if (!response.ok) {
                if ([409, 422].includes(response.status) && response.data?.error) this.clearPending();
                this.say(this.pending ? 'Connection interrupted. Recover Spin retrieves this same play without a second charge.' : response.error || 'This spin was refused.');
                return;
            }
            const { wallet, round } = response.data.response || {};
            if (!wallet || !round || round.requestKey !== this.pending.requestKey || round.wager !== this.pending.stake
                || !Array.isArray(round.wins) || !Number.isSafeInteger(round.payout) || round.payout < 0 || round.net !== round.payout - round.wager || !this.validRound(round)) throw new Error('Incomplete result');
            clearTimeout(timeout);
            const power = this.root.querySelector('.VLTPower'); power.textContent = `${this.presentation.feature.toUpperCase()} · ${round.multiplier}×`; power.classList.toggle('Charged', round.multiplier > 1);
            this.scene.excite(round.multiplier); this.audio.play('spin');
            await this.animateRound(round, signal);
            if (signal?.aborted) return;
            this.wallet.update(wallet); this.result(round); this.clearPending();
        } catch {
            if (!signal?.aborted) this.say('Your spin needs confirmation. Recover Spin retrieves its result.');
        } finally {
            clearTimeout(timeout); signal?.removeEventListener('abort', abort); this.busy = false;
            if (!signal?.aborted) { this.wallet.resume(); this.controls(); }
        }
    }
    validRound(round) {
        return Array.isArray(round.grid) && round.grid.length === 5 && round.grid.every(reel => Array.isArray(reel) && reel.length === 3 && reel.every(symbol => Number.isInteger(symbol) && symbol >= 0 && symbol < this.symbols.length)) && [1, 2, 5].includes(round.multiplier);
    }
    async animateRound(round, signal) { this.say('The reels are turning…'); await this.reels.spin(round.grid, signal); }
    renderWins(round) {
        const wins = this.root.querySelector('.VLTWins'); wins.replaceChildren();
        round.wins.forEach(win => {
            const button = node('button', '', `Line ${win.line + 1} · ${win.count} ${this.symbols[win.symbol].name} · ${win.payout.toLocaleString()}`);
            button.type = 'button'; button.setAttribute('aria-pressed', 'false'); button.addEventListener('click', () => this.highlight(round, win.line)); wins.append(button);
        });
        this.highlight(round);
    }
    clearPending() { this.storage.removeItem(this.storageKey); this.pending = null; }
}

ReadyHandler.add(() => {
    const root = document.querySelector('.VLTGame'); if (!root || root.dataset.vltGame !== 'vlt') return;
    const game = new VLTMachine(root); game.wallet.start();
});
