import { ReadyHandler } from '/scripts/Runtime.js';
import { VLTMachine, VLTAudio } from '/games/vlt.js';

function svg(tag, attributes = {}) {
    const element = document.createElementNS('http://www.w3.org/2000/svg', tag);
    for (const [name, value] of Object.entries(attributes)) element.setAttribute(name, value);
    return element;
}

export class PachinkoScene {
    constructor(root) {
        this.root = root; this.position = {x: 390, y: 40}; this.trail = []; this.impacts = [];
        this.reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.fallbackBoard();
        this.visibility = () => {
            window.cancelAnimationFrame(this.frame);
            if (!document.hidden && !this.disposed && this.renderer) this.loop(performance.now());
        };
        document.addEventListener('visibilitychange', this.visibility);
        this.reduced.addEventListener('change', this.visibility);
        this.ready = this.initialize().catch(() => this.fallback());
    }
    static pathPoints(path) {
        let right = 0;
        const points = [{x: 390, y: 40}];
        path.forEach((direction, row) => {
            points.push({x: 390 + (right - row / 2) * 50, y: 87 + row * 43});
            right += direction;
        });
        points.push({x: 390 + (right - 6) * 50, y: 652});
        return points;
    }
    fallbackBoard() {
        this.picture = svg('svg', {viewBox: '0 0 780 780', class: 'PachinkoFallback', 'aria-hidden': 'true'});
        this.picture.style.display = 'none';
        for (let row = 0; row < 12; row++) for (let column = 0; column <= row; column++) {
            this.picture.append(svg('circle', {cx: 390 + (column - row / 2) * 50, cy: 100 + row * 43, r: 5, fill: row % 2 ? '#ff71ed' : '#8affff'}));
        }
        this.fallbackBall = svg('circle', {cx: 390, cy: 40, r: 10, fill: '#f5ffff', stroke: '#78dcff', 'stroke-width': 3});
        this.picture.append(this.fallbackBall); this.root.prepend(this.picture);
    }
    fallback() {
        if (this.disposed) return;
        this.failed = true; window.cancelAnimationFrame(this.frame);
        this.renderer?.domElement.remove(); this.picture.style.display = 'block';
        this.root.querySelector('.PachinkoLoading')?.remove(); this.draw();
    }
    async initialize() {
        const T = await import('https://cdn.jsdelivr.net/npm/three@0.180.0/build/three.module.js');
        if (this.disposed) return;
        this.T = T;
        this.renderer = new T.WebGLRenderer({antialias: true, alpha: true, powerPreference: 'low-power'});
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
        this.renderer.toneMapping = T.ACESFilmicToneMapping; this.renderer.toneMappingExposure = 1.25;
        this.scene = new T.Scene();
        this.camera = new T.OrthographicCamera(-6.5, 6.5, 6.5, -6.5, .1, 60); this.camera.position.z = 22;
        this.scene.add(new T.HemisphereLight('#d4ffff', '#150c31', 2));
        const light = new T.DirectionalLight('#ffffff', 4); light.position.set(-4, 7, 12); this.scene.add(light);
        const pink = new T.PointLight('#ff36c8', 70, 24); pink.position.set(5, -1, 5); this.scene.add(pink);
        const cyan = new T.PointLight('#36f8ff', 70, 24); cyan.position.set(-5, 3, 5); this.scene.add(cyan);

        const environment = new T.Scene();
        for (const [x, y, z, color] of [[-7,0,2,'#afffff'],[7,0,2,'#ff8fdf'],[0,7,3,'#ffffff'],[0,-7,3,'#c9baff'],[0,0,10,'#ffffff']]) {
            const panel = new T.Mesh(new T.PlaneGeometry(5, 12), new T.MeshBasicMaterial({color, side: T.DoubleSide}));
            panel.position.set(x, y, z); panel.lookAt(0, 0, 0); environment.add(panel);
        }
        const generator = new T.PMREMGenerator(this.renderer);
        this.environment = generator.fromScene(environment, .08); this.scene.environment = this.environment.texture;
        generator.dispose(); environment.traverse(item => { item.geometry?.dispose(); item.material?.dispose(); });
        const chrome = new T.MeshPhysicalMaterial({color: '#ddefff', metalness: 1, roughness: .13, clearcoat: 1});
        const dark = new T.MeshPhysicalMaterial({color: '#091323', metalness: .65, roughness: .28, clearcoat: 1});
        const mesh = (geometry, material, x, y, z) => {
            const item = new T.Mesh(geometry, material); item.position.set(x, y, z); this.scene.add(item); return item;
        };
        mesh(new T.BoxGeometry(12.7, 12.6, .28), dark, 0, 0, -.4);
        for (const x of [-6.1, 6.1]) {
            mesh(new T.BoxGeometry(.2, 12.1, .35), chrome, x, 0, 0);
            mesh(new T.BoxGeometry(.045, 11.8, .08), new T.MeshBasicMaterial({color: x < 0 ? '#4cffff' : '#ff4ecb'}), x * .98, 0, .2);
        }
        for (const y of [-6, 6]) mesh(new T.BoxGeometry(12.3, .18, .35), chrome, 0, y, 0);
        mesh(new T.TorusGeometry(.24, .06, 12, 40), chrome, 0, 5.85, .2);

        const texture = document.createElement('canvas'); texture.width = texture.height = 64;
        const context = texture.getContext('2d'); const gradient = context.createRadialGradient(32, 32, 0, 32, 32, 32);
        gradient.addColorStop(0, '#ffffffff'); gradient.addColorStop(.2, '#ffffffaa'); gradient.addColorStop(1, '#ffffff00');
        context.fillStyle = gradient; context.fillRect(0, 0, 64, 64);
        this.glowTexture = new T.CanvasTexture(texture);
        const pins = new T.InstancedMesh(new T.SphereGeometry(.077, 12, 10), chrome, 78);
        const collars = new T.InstancedMesh(new T.TorusGeometry(.12, .026, 8, 16), new T.MeshBasicMaterial({color: '#ffffff'}), 78);
        const transform = new T.Object3D(); const glows = []; const colors = []; let index = 0;
        for (let row = 0; row < 12; row++) for (let column = 0; column <= row; column++) {
            const x = (column - row / 2) * 50 / 60, y = (290 - row * 43) / 60;
            transform.position.set(x, y, .16); transform.updateMatrix(); pins.setMatrixAt(index, transform.matrix);
            transform.position.z = .04; transform.updateMatrix(); collars.setMatrixAt(index, transform.matrix);
            const color = new T.Color(row % 2 ? '#ff63d8' : '#65ffff'); collars.setColorAt(index, color);
            glows.push(x, y, .05); colors.push(color.r, color.g, color.b); index++;
        }
        pins.instanceMatrix.needsUpdate = true; collars.instanceMatrix.needsUpdate = true; collars.instanceColor.needsUpdate = true;
        this.scene.add(pins, collars);
        const glowGeometry = new T.BufferGeometry(); glowGeometry.setAttribute('position', new T.Float32BufferAttribute(glows, 3)); glowGeometry.setAttribute('color', new T.Float32BufferAttribute(colors, 3));
        this.scene.add(new T.Points(glowGeometry, new T.PointsMaterial({size: 22, map: this.glowTexture, transparent: true, opacity: .6, vertexColors: true, blending: T.AdditiveBlending, depthWrite: false})));

        this.pockets = [];
        for (let pocket = 0; pocket < 13; pocket++) {
            const edge = Math.min(pocket, 12 - pocket);
            const material = new T.MeshPhysicalMaterial({color: edge === 0 ? '#ffa044' : edge < 3 ? '#e354dc' : '#22b9d1', metalness: .6, roughness: .23, emissive: edge === 0 ? '#ff7619' : '#2266a0', emissiveIntensity: .25});
            this.pockets.push(mesh(new T.BoxGeometry(.77, .65, .2), material, (pocket - 6) * 50 / 60, -4.65, 0));
        }
        this.ball = mesh(new T.SphereGeometry(.16, 28, 20), chrome, 0, 5.83, .3);
        this.trailSprites = [];
        for (let i = 0; i < 22; i++) {
            const sprite = new T.Sprite(new T.SpriteMaterial({map: this.glowTexture, color: i % 2 ? '#ff76dc' : '#75ffff', transparent: true, blending: T.AdditiveBlending, depthWrite: false, opacity: 0}));
            sprite.scale.setScalar(.35); this.scene.add(sprite); this.trailSprites.push(sprite);
        }
        this.rings = [];
        for (let i = 0; i < 12; i++) {
            const ring = mesh(new T.RingGeometry(.9, 1, 48), new T.MeshBasicMaterial({color: i % 2 ? '#ff7bdb' : '#8bffff', transparent: true, opacity: 0, blending: T.AdditiveBlending, depthWrite: false, side: T.DoubleSide}), 0, 0, .4);
            this.rings.push(ring);
        }
        this.sparks = new T.Points(new T.BufferGeometry(), new T.PointsMaterial({size: 5, map: this.glowTexture, color: '#ffe6b0', transparent: true, opacity: 0, blending: T.AdditiveBlending, depthWrite: false}));
        this.sparks.geometry.setAttribute('position', new T.BufferAttribute(new Float32Array(180 * 3), 3)); this.sparks.frustumCulled = false; this.scene.add(this.sparks);
        // A faint front reflection gives the cabinet a glass cover without obscuring pins.
        mesh(new T.PlaneGeometry(11.8, 11.8), new T.MeshBasicMaterial({color: '#b6eaff', transparent: true, opacity: .025, depthWrite: false}), 0, 0, .7);
        const canvas = this.renderer.domElement; canvas.setAttribute('aria-hidden', 'true');
        canvas.addEventListener('webglcontextlost', event => { event.preventDefault(); this.fallback(); });
        this.root.prepend(canvas);
        this.resize = new ResizeObserver(() => {
            if (this.disposed || this.failed) return;
            const size = this.root.clientWidth;
            if (size) { this.renderer.setSize(size, size, false); this.draw(); }
        });
        this.resize.observe(this.root);
        this.renderer.setSize(this.root.clientWidth || 600, this.root.clientWidth || 600, false);
        this.draw(); this.root.querySelector('.PachinkoLoading')?.remove(); this.visibility();
    }
    move(position) {
        this.position = position; this.trail.unshift({...position}); this.trail.length = Math.min(22, this.trail.length);
        if (this.failed || this.reduced.matches) this.draw();
    }
    impact(point) {
        if (this.reduced.matches) return;
        this.impacts.unshift({...point, at: performance.now()}); this.impacts.length = Math.min(12, this.impacts.length);
    }
    excite() {
        this.burst = null; this.impacts = []; this.trail = [];
        this.root.classList.remove('Jackpot');
    }
    celebrate(ratio) {
        if (this.reduced.matches) return;
        this.burst = {at: performance.now(), ratio, ...this.position};
        if (ratio >= 50) this.root.classList.add('Jackpot');
    }
    draw(now = performance.now()) {
        this.fallbackBall.setAttribute('cx', this.position.x); this.fallbackBall.setAttribute('cy', this.position.y);
        if (!this.renderer || this.disposed || this.failed || !this.ball) return;
        this.ball.position.set((this.position.x - 390) / 60, (390 - this.position.y) / 60, .32);
        this.ball.rotation.x = this.position.y / 15; this.ball.rotation.y = this.position.x / 15;
        this.trailSprites.forEach((sprite, index) => {
            const point = this.trail[index]; sprite.material.opacity = point && !this.reduced.matches ? .45 * (1 - index / 22) : 0;
            if (point) sprite.position.set((point.x - 390) / 60, (390 - point.y) / 60, .25);
        });
        this.rings.forEach((ring, index) => {
            const hit = this.impacts[index]; const age = hit ? (now - hit.at) / 700 : 2;
            ring.material.opacity = age < 1 && !this.reduced.matches ? .65 * (1 - age) : 0;
            if (hit) { ring.position.set((hit.x - 390) / 60, (390 - hit.y) / 60, .35); ring.scale.setScalar(.1 + age * .9); }
        });
        const age = this.burst ? (now - this.burst.at) / 1000 : 5;
        this.sparks.material.opacity = age < 2.8 && !this.reduced.matches ? 1 - age / 2.8 : 0;
        if (age < 2.8 && this.burst) {
            const positions = this.sparks.geometry.attributes.position;
            const force = this.burst.ratio >= 50 ? 4 : 2;
            for (let i = 0; i < 180; i++) {
                const angle = i * 2.39996, speed = 1 + i % 11 / 4;
                positions.setXYZ(i, (this.burst.x - 390) / 60 + Math.cos(angle) * age * speed * force,
                    (390 - this.burst.y) / 60 + Math.abs(Math.sin(angle)) * age * speed * force - age * age * 2, .5);
            }
            positions.needsUpdate = true;
        }
        this.renderer.render(this.scene, this.camera);
    }
    loop(now) {
        if (this.disposed || this.failed || document.hidden) return;
        if (!this.lastFrame || now - this.lastFrame >= 1000 / 30) { this.draw(now); this.lastFrame = now; }
        if (!this.reduced.matches) this.frame = window.requestAnimationFrame(time => this.loop(time));
    }
    async drop(path, signal, onImpact = () => {}) {
        if (signal?.aborted) return;
        const points = PachinkoScene.pathPoints(path); this.trail = [];
        if (this.reduced.matches) { this.move(points.at(-1)); return; }
        await new Promise(resolve => {
            let frame, elapsed = 0, previous = null, hit = 0;
            const finish = () => { window.cancelAnimationFrame(frame); signal?.removeEventListener('abort', finish); resolve(); };
            signal?.addEventListener('abort', finish, {once: true});
            const tick = now => {
                if (signal?.aborted || this.disposed) { finish(); return; }
                if (previous !== null && !document.hidden) elapsed += Math.min(60, now - previous);
                previous = now;
                const progress = Math.min(points.length - 1, elapsed / 290);
                const step = Math.min(points.length - 2, Math.floor(progress)); const fraction = progress - step;
                const from = points[step], to = points[step + 1];
                this.move({x: from.x + (to.x - from.x) * fraction, y: from.y + (to.y - from.y) * fraction - (step ? Math.sin(fraction * Math.PI) * 16 : 0)});
                const reached = Math.min(12, Math.floor(progress));
                while (hit < reached) { hit++; this.impact({x: points[hit].x, y: points[hit].y + 13}); onImpact(hit); }
                if (progress >= points.length - 1) { this.trail = []; this.move(points.at(-1)); finish(); return; }
                if (this.reduced.matches) { this.trail = []; this.move(points.at(-1)); finish(); return; }
                frame = window.requestAnimationFrame(tick);
            };
            frame = window.requestAnimationFrame(tick);
        });
    }
    dispose() {
        this.disposed = true; window.cancelAnimationFrame(this.frame); this.resize?.disconnect();
        document.removeEventListener('visibilitychange', this.visibility); this.reduced.removeEventListener('change', this.visibility);
        const materials = new Set(), geometries = new Set();
        this.scene?.traverse(item => { if (item.geometry) geometries.add(item.geometry); if (item.material) materials.add(item.material); if (item.isInstancedMesh) item.dispose(); });
        geometries.forEach(geometry => geometry.dispose()); materials.forEach(material => material.dispose());
        this.environment?.dispose(); this.glowTexture?.dispose(); this.renderer?.dispose(); this.renderer?.domElement.remove();
    }
}

export class PachinkoMachine extends VLTMachine {
    constructor(root, options = {}) {
        const scene = options.scene || new PachinkoScene(root.querySelector('.PachinkoBoard'));
        super(root, {...options, scene, reels: {display() {}, reduced: scene.reduced}, AudioType: VLTAudio});
        this.returns = JSON.parse(root.querySelector('.PachinkoBoard').dataset.returns);
        root.querySelector('.VLTPower').textContent = 'JACKPOT · 50×';
    }
    guide() {}
    validRound(round) {
        return Array.isArray(round.path) && round.path.length === 12 && round.path.every(direction => direction === 0 || direction === 1)
            && round.pocket === round.path.reduce((sum, direction) => sum + direction, 0)
            && round.multiplier === this.returns[round.pocket] / 10 && round.payout === round.wager / 10 * this.returns[round.pocket];
    }
    async animateRound(round, signal) {
        this.root.querySelectorAll('.PachinkoPocket').forEach(pocket => pocket.classList.remove('Landed'));
        const power = this.root.querySelector('.VLTPower'); power.textContent = 'BALL IN PLAY'; power.classList.remove('Charged');
        this.say('Follow the chrome…');
        await this.scene.drop(round.path, signal, count => this.audio.play('stop', count));
        if (signal?.aborted) return;
        this.root.querySelectorAll('.PachinkoPocket')[round.pocket].classList.add('Landed');
        power.textContent = `POCKET ${round.pocket + 1} · ${round.multiplier}×`; power.classList.toggle('Charged', round.multiplier > 1);
    }
    renderWins(round) {
        const wins = this.root.querySelector('.VLTWins'); wins.replaceChildren();
        const receipt = document.createElement('p'); receipt.className = 'PachinkoReceipt';
        receipt.textContent = `Pocket ${round.pocket + 1} · ${round.multiplier}× stake · ${round.path.filter(direction => direction === 0).length} left / ${round.pocket} right`;
        wins.append(receipt);
    }
}

ReadyHandler.add(() => {
    const root = document.querySelector('.PachinkoGame'); if (!root) return;
    const game = new PachinkoMachine(root); game.wallet.start();
});
