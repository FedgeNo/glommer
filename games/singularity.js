import { ReadyHandler } from '/scripts/Runtime.js';
import { VLTMachine, VLTScene, VLTSymbol, VLTAudio, VLTReels } from '/games/vlt.js';

function art(tag, attributes = {}) {
    const result = document.createElementNS('http://www.w3.org/2000/svg', tag);
    for (const [key, value] of Object.entries(attributes)) result.setAttribute(key, String(value));
    return result;
}

export class SingularitySymbol extends VLTSymbol {
    toDOM() {
        const root = super.toDOM();
        root.classList.add('SingularitySymbol');
        const drawing = root.querySelector('svg'); const group = drawing.querySelector('g'); group.replaceChildren();
        const color = ['#ff9a51', '#9af6ff', '#c6ff7b', '#ffdd85', '#ff8873', '#ffffb1'][this.symbol];
        group.setAttribute('stroke', color); drawing.querySelector('stop[offset=".4"]').setAttribute('stop-color', color);
        if (this.symbol === 0) {
            group.append(art('path', {d: 'M50 4 82 31 72 62 50 95 18 63 23 29Z'}));
            group.append(art('path', {d: 'M57 17 31 51H48L41 80 70 43H51Z', fill: '#fff9d5', stroke: '#fffded'}));
        } else if (this.symbol === 1) {
            for (let i = 0; i < 8; i++) group.append(art('path', {d: 'M45 6H58L63 34 49 42 40 27Z', transform: `rotate(${i * 45} 50 50)`}));
            group.append(art('circle', {cx: 50, cy: 50, r: 21, fill: '#19121f'}), art('circle', {cx: 50, cy: 50, r: 12, fill: '#fff6cb'}));
        } else if (this.symbol === 2) {
            group.append(art('path', {d: 'M50 5 85 25V75L50 95 15 75V25Z'}));
            group.append(art('path', {d: 'M50 17 73 31V69L50 82 27 69V31Z', fill: '#152924', stroke: '#deffb1'}));
            for (let i = 0; i < 3; i++) group.append(art('path', {d: `M37 ${36 + i * 14}H63`, stroke: '#e8ff87', 'stroke-width': 7}));
        } else if (this.symbol === 3) {
            for (let i = 0; i < 3; i++) group.append(art('ellipse', {cx: 50, cy: 50, rx: 43, ry: 16, transform: `rotate(${i * 60} 50 50)`, fill: 'none', 'stroke-width': 4}));
            group.append(art('circle', {cx: 50, cy: 50, r: 12, fill: '#fffbe4'}));
            for (let i = 0; i < 3; i++) group.append(art('circle', {cx: 91, cy: 50, r: 5, transform: `rotate(${i * 120} 50 50)`, fill: '#fff'}));
        } else if (this.symbol === 4) {
            group.append(art('circle', {cx: 50, cy: 50, r: 43, fill: '#241517', 'stroke-width': 5}));
            for (let i = 0; i < 3; i++) group.append(art('path', {d: 'M44 34 32 14Q50 4 68 14L56 34Z', transform: `rotate(${i * 120} 50 50)`}));
            group.append(art('circle', {cx: 50, cy: 50, r: 11, fill: '#fff4a1'}));
        } else {
            for (let i = 0; i < 12; i++) group.append(art('path', {d: 'M48 4 56 25 50 31 42 21Z', transform: `rotate(${i * 30} 50 50)`}));
            group.append(art('circle', {cx: 50, cy: 50, r: 26, fill: '#0a0714', 'stroke-width': 5}));
            group.append(art('ellipse', {cx: 50, cy: 50, rx: 41, ry: 10, fill: 'none', transform: 'rotate(-25 50 50)', stroke: '#fff3bb', 'stroke-width': 4}));
        }
        return root;
    }
}

export class SingularityAudio extends VLTAudio {
    play(kind, level = 1) {
        super.play(kind, level);
        if (!this.enabled || document.hidden || this.context?.state !== 'running' || kind === 'stop') return;
        const oscillator = this.context.createOscillator(); const gain = this.context.createGain();
        const start = this.context.currentTime;
        oscillator.type = 'sine'; oscillator.frequency.setValueAtTime(kind === 'spin' ? 45 : 110, start);
        oscillator.frequency.exponentialRampToValueAtTime(kind === 'spin' ? 180 : 35, start + .65);
        gain.gain.setValueAtTime(.0001, start); gain.gain.exponentialRampToValueAtTime(.09, start + .04);
        gain.gain.exponentialRampToValueAtTime(.0001, start + .8);
        oscillator.connect(gain); gain.connect(this.context.destination); oscillator.start(start); oscillator.stop(start + .85);
        oscillator.onended = () => { oscillator.disconnect(); gain.disconnect(); };
    }
}

export class SingularityScene extends VLTScene {
    async initialize() {
        await super.initialize(); if (this.disposed) return;
        const T = this.T;
        this.vortex = new T.Mesh(new T.PlaneGeometry(30, 30), new T.ShaderMaterial({
            transparent: true, depthWrite: false, blending: T.AdditiveBlending,
            uniforms: {time: {value: 0}, power: {value: 0}},
            vertexShader: 'varying vec2 point; void main() { point = uv * 2.0 - 1.0; gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0); }',
            fragmentShader: `
                varying vec2 point;
                uniform float time;
                uniform float power;
                void main() {
                    float radius = length(point);
                    float angle = atan(point.y, point.x);
                    float swirl = angle * 8.0 - log(max(radius, 0.02)) * 19.0 + time * (0.7 + power * 0.15);
                    float threads = pow(0.5 + 0.5 * sin(swirl), 7.0);
                    float fine = pow(0.5 + 0.5 * sin(swirl * 2.3 - time), 18.0);
                    float disk = exp(-abs(radius - 0.22) * 15.0);
                    float rim = exp(-abs(radius - 0.10) * 110.0);
                    float aperture = smoothstep(0.085, 0.11, radius);
                    vec3 hot = mix(vec3(0.1, 0.85, 1.0), vec3(1.0, 0.22, 0.04), 0.5 + 0.5 * sin(angle * 2.0 + time * 0.1));
                    vec3 color = hot * (threads * disk * 0.8 + fine * disk * 0.45) + vec3(1.0, 0.7, 0.3) * rim;
                    float fade = (1.0 - smoothstep(0.4, 0.75, radius)) * aperture;
                    gl_FragColor = vec4(color * (0.8 + power * 0.12), fade * 0.85);
                }`,
        }));
        this.vortex.position.z = -4; this.scene.add(this.vortex);
        this.rotors = new T.Group(); this.scene.add(this.rotors);
        const segment = new T.BoxGeometry(.13, .44, .2);
        for (let layer = 0; layer < 3; layer++) {
            const rotor = new T.Group();
            const teeth = new T.InstancedMesh(segment, new T.MeshStandardMaterial({ color: layer === 1 ? '#b0ebee' : '#b89261', metalness: .85, roughness: .28, emissive: layer === 1 ? '#165d64' : '#50250d' }), 36);
            const transform = new T.Object3D();
            for (let index = 0; index < 36; index++) {
                const angle = index / 36 * Math.PI * 2;
                transform.position.set(Math.cos(angle) * (3.2 + layer * .45), Math.sin(angle) * (3.2 + layer * .45), 0);
                transform.rotation.z = angle - Math.PI / 2; transform.updateMatrix(); teeth.setMatrixAt(index, transform.matrix);
            }
            teeth.instanceMatrix.needsUpdate = true;
            rotor.add(teeth); rotor.rotation.x = .35 + layer * .2; rotor.rotation.y = layer * .25;
            this.rotors.add(rotor);
        }
        this.plasma = new T.Group(); this.scene.add(this.plasma);
        for (let index = 0; index < 8; index++) {
            const geometry = new T.BufferGeometry(); geometry.setAttribute('position', new T.BufferAttribute(new Float32Array(41 * 3), 3));
            const arc = new T.Line(geometry, new T.LineBasicMaterial({ color: index % 2 ? '#ffb155' : '#71faff', transparent: true, opacity: .7, blending: T.AdditiveBlending, depthWrite: false, toneMapped: false }));
            arc.frustumCulled = false; this.plasma.add(arc);
        }
        this.shockwaves = new T.Group(); this.scene.add(this.shockwaves);
        for (let index = 0; index < 4; index++) {
            const wave = new T.Mesh(new T.RingGeometry(.94, 1, 96), new T.MeshBasicMaterial({ color: index % 2 ? '#ffad48' : '#91ffff', transparent: true, opacity: 0, side: T.DoubleSide, blending: T.AdditiveBlending, depthWrite: false, toneMapped: false }));
            this.shockwaves.add(wave);
        }
        this.draw();
    }
    excite(amount) { super.excite(amount * 1.5); this.root.closest('.VLTCabinet')?.classList.add('Igniting'); }
    celebrate(ratio) {
        super.celebrate(ratio); this.eruptionAt = performance.now();
        this.root.closest('.VLTCabinet')?.classList.remove('Igniting');
    }
    draw() {
        if (this.vortex && !this.disposed) {
            const now = this.reduced.matches ? 0 : performance.now() / 1000;
            this.vortex.material.uniforms.time.value = now;
            this.vortex.material.uniforms.power.value = this.energy;
            this.rotors.children.forEach((rotor, index) => { rotor.rotation.z = now * (index % 2 ? -.14 : .1) * (1 + this.energy * .15); });
            this.plasma.children.forEach((arc, index) => {
                const positions = arc.geometry.attributes.position;
                for (let step = 0; step <= 40; step++) {
                    const fraction = step / 40;
                    const angle = index * Math.PI / 4 + fraction * 1.1 + Math.sin(now * .7 + index) * .15;
                    const radius = 1.1 + fraction * 4.5;
                    const ripple = Math.sin(step * 2.1 + now * 6) * .065 * Math.sin(fraction * Math.PI);
                    positions.setXYZ(step, Math.cos(angle) * radius + ripple, Math.sin(angle) * radius + ripple, .1);
                }
                positions.needsUpdate = true;
            });
            if (this.eruptionAt && !this.reduced.matches) {
                const age = (performance.now() - this.eruptionAt) / 1000;
                this.shockwaves.children.forEach((wave, index) => {
                    const progress = Math.max(0, age - index * .3);
                    wave.scale.setScalar(.5 + progress * 4);
                    wave.material.opacity = progress > 0 && progress < 1.7 ? .45 * (1 - progress / 1.7) : 0;
                });
            }
        }
        super.draw();
    }
}

export class SingularityBoard extends VLTReels {
    async motion(entries, signal) {
        if (signal?.aborted || this.reduced.matches) return;
        const animations = entries.filter(([element]) => element.animate).map(([element, frames, duration]) => element.animate(frames, {duration, easing: 'cubic-bezier(.2,.7,.3,1)', fill: 'both'}));
        const abort = () => animations.forEach(animation => animation.cancel());
        signal?.addEventListener('abort', abort, {once: true});
        try { await Promise.all(animations.map(animation => animation.finished.catch(() => {}))); }
        finally { signal?.removeEventListener('abort', abort); abort(); }
    }
    async drop(grid, clusters, signal) {
        if (signal?.aborted) return;
        this.display(grid);
        const entries = [];
        this.root.querySelectorAll('.VLTReel').forEach((column, x) => {
            const removed = new Set(clusters.flatMap(cluster => cluster.cells.filter(cell => cell[0] === x).map(cell => cell[1])));
            const survivors = Array.from({length: 5}, (_, y) => y).filter(y => !removed.has(y));
            Array.from(column.children).forEach((element, y) => {
                const from = clusters.length ? y < removed.size ? y - removed.size - 1 : survivors[y - removed.size] : y - 5;
                if (from !== y) entries.push([element, [{transform: `translateY(${(from - y) * 100}%)`, opacity: y < removed.size || !clusters.length ? .1 : 1}, {transform: 'translateY(0)', opacity: 1}], 450 + x * 45]);
            });
        });
        await this.motion(entries, signal);
    }
    async explode(clusters, signal) {
        const columns = this.root.querySelectorAll('.VLTReel');
        const cells = clusters.flatMap(cluster => cluster.cells.map(([x, y]) => columns[x].children[y]));
        cells.forEach(cell => cell.classList.add('Winning'));
        try {
            await this.motion(cells.map(cell => [cell, [{transform: 'scale(1)', opacity: 1}, {transform: 'scale(1.12)', opacity: 1, offset: .65}, {transform: 'scale(.05)', opacity: 0}], 750]), signal);
        } finally { cells.forEach(cell => cell.classList.remove('Winning')); }
    }
}

export class SingularityMachine extends VLTMachine {
    constructor(root, options = {}) {
        const board = new SingularityBoard(root, JSON.parse(root.dataset.symbols), null, SingularitySymbol);
        super(root, { ...options, reels: board, scene: options.scene || new SingularityScene(root.querySelector('.VLTScene')), SymbolType: SingularitySymbol, AudioType: SingularityAudio });
        this.effects = document.createElement('div'); this.effects.className = 'SingularityEruption'; this.effects.setAttribute('aria-hidden', 'true');
        root.append(this.effects);
        this.clearEffects = () => { this.effects.replaceChildren(); this.root.querySelector('.VLTCabinet').classList.remove('Igniting'); };
        window.addEventListener('pagehide', this.clearEffects);
    }
    guide() {}
    validRound(round) {
        if (round.version !== 2) return super.validRound(round);
        const grid = value => Array.isArray(value) && value.length === 5 && value.every(column => Array.isArray(column) && column.length === 5 && column.every(symbol => Number.isInteger(symbol) && symbol >= 0 && symbol < this.symbols.length));
        const cluster = value => Number.isInteger(value.symbol) && value.symbol >= 0 && value.symbol < this.symbols.length
            && Array.isArray(value.cells) && value.cells.length >= 5 && value.cells.length <= 25 && value.count === value.cells.length
            && value.cells.every(cell => Array.isArray(cell) && cell.length === 2 && cell.every(coordinate => Number.isInteger(coordinate) && coordinate >= 0 && coordinate < 5))
            && Number.isSafeInteger(value.payout) && value.payout > 0;
        return grid(round.grid) && Array.isArray(round.cascades) && round.cascades.length <= 12
            && round.multiplier === Math.max(1, round.cascades.length)
            && round.cascades.every((step, index) => grid(step.grid) && step.multiplier === index + 1 && Array.isArray(step.clusters) && step.clusters.length > 0
                && step.clusters.every(cluster) && step.payout === step.clusters.reduce((sum, win) => sum + win.payout, 0))
            && round.payout === round.cascades.reduce((sum, step) => sum + step.payout, 0);
    }
    async animateRound(round, signal) {
        if (round.version !== 2) { this.reels.display(round.grid); return; }
        const power = this.root.querySelector('.VLTPower');
        power.textContent = 'OVERDRIVE · 1×'; power.classList.remove('Charged');
        this.say('The reactor is filling…');
        await this.reels.drop(round.cascades[0]?.grid || round.grid, [], signal);
        let total = 0;
        for (let index = 0; index < round.cascades.length; index++) {
            if (signal?.aborted) return;
            const step = round.cascades[index]; total += step.payout;
            power.textContent = `OVERDRIVE · ${step.multiplier}×`; power.classList.toggle('Charged', step.multiplier > 1);
            this.root.querySelector('.VLTWinTitle').textContent = `CHAIN REACTION ${step.multiplier}`;
            this.root.querySelector('.VLTWinAmount').textContent = total.toLocaleString();
            this.say(`Cascade ${step.multiplier} · ${step.clusters.length} winning group${step.clusters.length === 1 ? '' : 's'} · ${step.payout.toLocaleString()} chips returned.`);
            this.scene.excite(step.multiplier); this.audio.play('stop', step.multiplier);
            await this.reels.explode(step.clusters, signal);
            if (signal?.aborted) return;
            if (index === 11) this.reels.display(round.grid);
            else await this.reels.drop(round.cascades[index + 1]?.grid || round.grid, step.clusters, signal);
        }
    }
    renderWins(round) {
        const list = this.root.querySelector('.VLTWins'); list.replaceChildren();
        if (round.version !== 2) {
            const note = document.createElement('p'); note.textContent = 'Recovered result from the previous reel game.'; list.append(note); return;
        }
        round.cascades.forEach(step => {
            const item = document.createElement('p'); item.className = 'SingularityCascadeReceipt';
            item.textContent = `${step.multiplier}× · ${step.clusters.map(cluster => `${cluster.count} ${this.symbols[cluster.symbol].name}`).join(' + ')} · ${step.payout.toLocaleString()} chips`;
            list.append(item);
        });
    }
    async spin(signal) {
        if (!this.busy) this.clearEffects();
        try { await super.spin(signal); } finally { this.root.querySelector('.VLTCabinet').classList.remove('Igniting'); }
    }
    result(round) {
        super.result(round);
        this.effects.replaceChildren();
        if (round.net <= 0 || this.reels.reduced.matches) return;
        const ratio = round.payout / round.wager;
        const count = ratio >= 20 ? 70 : ratio >= 5 ? 44 : 22;
        for (let index = 0; index < count; index++) {
            const shard = document.createElement('span'); shard.className = 'SingularityShard';
            shard.style.setProperty('--x', (Math.random() * 110 - 55) + 'vw');
            shard.style.setProperty('--y', (Math.random() * 110 - 65) + 'vh');
            shard.style.setProperty('--rotation', (Math.random() * 1080 - 540) + 'deg');
            shard.style.setProperty('--color', ['#ff9c49', '#8efaff', '#ffe99a', '#ff7197'][index % 4]);
            shard.style.animationDelay = (index % 7 * .04) + 's';
            shard.addEventListener('animationend', () => shard.remove(), {once: true}); this.effects.append(shard);
        }
        if (ratio >= 5) {
            const wave = document.createElement('div'); wave.className = 'SingularityScreenWave';
            wave.addEventListener('animationend', () => wave.remove(), {once: true}); this.effects.append(wave);
        }
    }
}

ReadyHandler.add(() => {
    const root = document.querySelector('.SingularityGame'); if (!root) return;
    const game = new SingularityMachine(root); game.wallet.start();
});
