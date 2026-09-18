import { Api, ReadyHandler } from '/scripts/Runtime.js';
import { Avatar, FormForm } from '/scripts/HTMLObjects.js';
import { CasinoWallet } from '/games/casino.js';

function element(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
}

export class PokerCard {
    constructor(value) { this.value = value; }
    toDOM() {
        const card = element('span', 'PokerCard');
        if (this.value === null) {
            card.classList.add('Back'); card.textContent = '◆'; card.setAttribute('aria-label', 'Face-down card');
        } else {
            const suit = Math.floor(this.value / 13);
            const rank = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'][this.value % 13];
            if (suit === 1 || suit === 2) card.classList.add('Red');
            card.append(element('span', '', rank), element('span', '', ['♠', '♥', '♦', '♣'][suit]));
            card.setAttribute('aria-label', `${rank} of ${['spades', 'hearts', 'diamonds', 'clubs'][suit]}`);
        }
        return card;
    }
}

export class PokerIdentity {
    constructor(player) { this.player = player; }
    node(className, text) {
        const root = element(this.player.userId === null ? 'span' : 'a', className, text);
        if (this.player.userId !== null) root.href = '/users/' + encodeURIComponent(this.player.name.slice(1)) + '/';
        return root;
    }
    nameDOM() { return this.node('PokerName', this.player.name); }
    toDOM() {
        const root = this.node('PokerIdentity');
        root.append(this.player.userId === null
            ? element('span', 'PokerBotAvatar', this.player.name[0])
            : Avatar.create(Boolean(this.player.image), this.player.image, this.player.name.slice(1), this.player.userId).toDOM());
        root.append(element('span', 'PokerName', this.player.name));
        root.title = this.player.userId === null ? `${this.player.name} · Computer Opponent` : this.player.name;
        return root;
    }
}

/** Reflective rail, soft table lighting, and animated physical chip stacks. */
export class PokerScene {
    constructor(root) {
        this.root = root;
        this.disposed = false;
        this.frame = null;
        this.ready = this.initialize().catch(() => { this.dispose(); });
    }
    async initialize() {
        const T = await import('https://cdn.jsdelivr.net/npm/three@0.180.0/build/three.module.js');
        if (this.disposed) return;
        this.T = T;
        this.renderer = new T.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' });
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = T.PCFSoftShadowMap;
        this.renderer.toneMapping = T.ACESFilmicToneMapping;
        this.scene = new T.Scene();
        this.camera = new T.PerspectiveCamera(38, 1, .1, 40);
        this.camera.position.set(0, 11, 3.2);
        this.camera.lookAt(0, 0, 0);
        this.scene.add(new T.HemisphereLight('#fff0d0', '#163b2c', 3));
        const light = new T.DirectionalLight('#fff1d6', 4);
        light.position.set(-3, 8, 3); light.castShadow = true;
        Object.assign(light.shadow.camera, { left: -6, right: 6, top: 5, bottom: -5 });
        light.shadow.mapSize.set(1024, 1024); light.shadow.normalBias = .03;
        this.scene.add(light);
        const surface = document.createElement('canvas'); surface.width = 256; surface.height = 256;
        const ctx = surface.getContext('2d');
        ctx.fillStyle = '#20543e'; ctx.fillRect(0, 0, 256, 256);
        for (let i = 0; i < 13000; i++) {
            ctx.fillStyle = i % 2 ? '#fff1' : '#0002'; ctx.fillRect(Math.random() * 256, Math.random() * 256, 1, 1);
        }
        this.texture = new T.CanvasTexture(surface);
        this.texture.wrapS = this.texture.wrapT = T.RepeatWrapping; this.texture.repeat.set(5, 5);
        this.table = new T.Group(); this.scene.add(this.table);
        const felt = new T.Mesh(new T.CylinderGeometry(2.7, 2.7, .18, 96), new T.MeshStandardMaterial({ map: this.texture, roughness: .95 }));
        felt.scale.x = 1.65; felt.receiveShadow = true; this.table.add(felt);
        const rail = new T.Mesh(new T.TorusGeometry(2.85, .23, 16, 100), new T.MeshPhysicalMaterial({ color: '#3b221a', roughness: .32, clearcoat: .8 }));
        rail.rotation.x = -Math.PI / 2; rail.scale.x = 1.65; rail.position.y = .12; rail.castShadow = true; this.table.add(rail);
        const trim = new T.Mesh(new T.TorusGeometry(2.62, .025, 8, 100), new T.MeshStandardMaterial({ color: '#c5a266', metalness: .7, roughness: .3 }));
        trim.rotation.x = -Math.PI / 2; trim.scale.x = 1.65; trim.position.y = .13; this.table.add(trim);
        this.chips = new T.Group(); this.table.add(this.chips);
        this.chipGeometry = new T.CylinderGeometry(.105, .105, .045, 24);
        this.chipMaterials = ['#c93b36', '#e5d4a6', '#235c8c'].map(color => new T.MeshStandardMaterial({ color, roughness: .5 }));
        this.root.append(this.renderer.domElement);
        this.root.closest('.PokerTable')?.classList.add('SceneReady');
        this.resize = new ResizeObserver(() => {
            const width = this.root.clientWidth || 800; const height = this.root.clientHeight || 400;
            this.camera.aspect = width / height;
            this.table.scale.x = this.camera.aspect * 1.1 / 1.65;
            this.camera.updateProjectionMatrix(); this.renderer.setSize(width, height, false); this.draw();
        });
        this.resize.observe(this.root);
        this.update(this.latest);
    }
    draw() { if (!this.disposed && !document.hidden) this.renderer?.render(this.scene, this.camera); }
    update(table) {
        this.latest = table;
        if (!this.T || this.disposed) return;
        this.chips.clear();
        const count = Math.min(36, Math.ceil((table?.pot || 0) / 50));
        for (let i = 0; i < count; i++) {
            const chip = new this.T.Mesh(this.chipGeometry, this.chipMaterials[Math.floor(i / 12) % 3]);
            chip.position.set((Math.floor(i / 12) - 1) * .25, .13 + (i % 12) * .045, -.85);
            chip.castShadow = true; this.chips.add(chip);
        }
        cancelAnimationFrame(this.frame);
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { this.chips.position.y = 0; this.draw(); return; }
        const start = performance.now();
        const animate = now => {
            if (this.disposed) return;
            const progress = Math.min(1, (now - start) / 350);
            this.chips.position.y = .5 * (1 - progress) ** 2; this.draw();
            if (progress < 1 && !document.hidden) this.frame = requestAnimationFrame(animate);
        };
        this.frame = requestAnimationFrame(animate);
    }
    dispose() {
        this.disposed = true; cancelAnimationFrame(this.frame); this.resize?.disconnect();
        this.scene?.traverse(item => { item.geometry?.dispose(); if (item.material) item.material.dispose(); });
        this.texture?.dispose(); this.chipGeometry?.dispose(); this.chipMaterials?.forEach(material => material.dispose());
        this.renderer?.dispose(); this.renderer?.domElement.remove();
        this.root.closest('.PokerTable')?.classList.remove('SceneReady');
    }
}

export class PokerSound {
    constructor(button) {
        this.button = button;
        this.enabled = false;
        try { this.enabled = localStorage.getItem('pokerSound') === 'on'; } catch {}
        this.label();
        button.addEventListener('click', () => {
            this.enabled = !this.enabled;
            try { localStorage.setItem('pokerSound', this.enabled ? 'on' : 'off'); } catch {}
            if (!this.enabled) this.stop();
            this.label(); this.unlock(); this.play('chip');
        });
    }
    label() { this.button.textContent = this.enabled ? 'Mute Sound' : 'Enable Sound'; this.button.setAttribute('aria-pressed', String(this.enabled)); }
    unlock() {
        if (!this.enabled) return;
        try {
            const Audio = window.AudioContext || window.webkitAudioContext;
            if (!Audio) return;
            this.context ||= new Audio();
            this.context.resume().catch(() => {});
        } catch {}
    }
    play(kind, delay = 0) {
        if (!this.enabled || document.hidden || this.context?.state !== 'running') return;
        const notes = kind === 'turn' ? [660, 880] : kind === 'win' ? [523, 659, 784] : kind === 'deal' ? [1800] : [1100, 1500];
        notes.forEach((frequency, index) => {
            const oscillator = this.context.createOscillator(); const gain = this.context.createGain();
            const start = this.context.currentTime + delay + index * .09;
            oscillator.type = kind === 'deal' ? 'triangle' : 'sine'; oscillator.frequency.setValueAtTime(frequency, start);
            gain.gain.setValueAtTime(.0001, start); gain.gain.exponentialRampToValueAtTime(.045, start + .008);
            gain.gain.exponentialRampToValueAtTime(.0001, start + .12);
            oscillator.connect(gain); gain.connect(this.context.destination);
            oscillator.start(start); oscillator.stop(start + .13);
            oscillator.onended = () => { oscillator.disconnect(); gain.disconnect(); };
        });
    }
    stop() { this.context?.close().catch(() => {}); this.context = null; }
}

export class PokerPresentation {
    constructor(root, sound) { this.root = root; this.sound = sound; this.previous = null; }
    position(index) {
        const seat = this.root.querySelectorAll('.PokerSeat')[index];
        return seat ? { x: parseFloat(seat.style.getPropertyValue('--seat-x')), y: parseFloat(seat.style.getPropertyValue('--seat-y')) } : { x: 50, y: 50 };
    }
    flight(from, to, label, delay = 0, dealer = false) {
        const chip = element('span', dealer ? 'PokerDealerFlight' : 'PokerChipFlight', label);
        chip.setAttribute('aria-hidden', 'true');
        for (const [key, value] of Object.entries({ 'from-x': from.x, 'from-y': from.y, 'to-x': to.x, 'to-y': to.y })) chip.style.setProperty('--' + key, value + '%');
        chip.style.animationDelay = delay + 's';
        chip.addEventListener('animationend', () => chip.remove(), { once: true });
        this.root.querySelector('.PokerTable').append(chip);
    }
    update(table, viewer) {
        const previous = this.previous;
        const same = table && previous?.tableId === table.tableId;
        if (!same) this.clear();
        if (!table) { this.previous = null; return; }
        const fresh = !same && table.street === 'preflop';
        const showdown = same && previous.street !== 'finished' && table.street === 'finished';
        const boardStart = same ? previous.board.length : table.board.length;
        const revealDelay = Math.max(0, table.board.length - boardStart) * .18;
        let reveal = 0;
        const seats = this.root.querySelectorAll('.PokerSeat');
        table.seats.forEach((player, index) => {
            const seat = seats[index];
            seat.classList.toggle('Winner', (table.pots || []).some(pot => !pot.refund && pot.winners.includes(index)));
            seat.querySelectorAll('.PokerCard').forEach((card, cardIndex) => {
                if (fresh) {
                    card.classList.add('Dealing');
                    card.style.animationDelay = (((index - table.dealer - 1 + seats.length) % seats.length) * .045 + cardIndex * .4) + 's';
                } else if (showdown && table.showdown && !player.folded && player.userId !== viewer) {
                    card.classList.add('Revealing'); card.style.animationDelay = (revealDelay + reveal * .18) + 's';
                }
            });
            if (!player.folded && player.userId !== viewer) reveal++;
            if (same) {
                const added = (player.total || 0) - (previous.seats[index].total || 0);
                const position = this.position(index);
                const betPosition = { x: 50 + (position.x - 50) * .70, y: 50 + (position.y - 50) * .63 };
                if (added > 0) this.flight(position, table.street === previous.street ? betPosition : { x: 50, y: 48 }, '+' + added);
                if (table.street !== previous.street && previous.seats[index].bet > 0) this.flight(betPosition, { x: 50, y: 48 }, String(previous.seats[index].bet));
            }
        });
        const awardDelay = showdown && table.showdown ? revealDelay + reveal * .18 + .4 : .3;
        if (showdown) {
            table.seats.forEach((player, index) => { if (player.won > 0) this.flight({ x: 50, y: 48 }, this.position(index), '+' + player.won, awardDelay); });
            const result = this.root.querySelector('.PokerResult');
            result.classList.add('Arriving'); result.style.animationDelay = awardDelay + 's';
            this.sound.play('win', awardDelay);
        }
        if (fresh && previous) this.flight(this.dealerPosition || { x: 50, y: 50 }, this.position(table.dealer), 'D', 0, true);
        this.root.querySelectorAll('.PokerBoard .PokerCard').forEach((card, index) => {
            if (index >= boardStart) { card.classList.add('Revealing'); card.style.animationDelay = ((index - boardStart) * .18) + 's'; }
        });
        if (fresh || table.board.length > boardStart) this.sound.play('deal');
        else if (same && table.version !== previous.version && !showdown) this.sound.play('chip');
        if (table.turn !== null && table.seats[table.turn]?.userId === viewer && (!same || table.version !== previous.version)) this.sound.play('turn');
        this.dealerPosition = this.position(table.dealer);
        this.previous = structuredClone(table);
    }
    clear() { this.root.querySelectorAll('.PokerChipFlight, .PokerDealerFlight').forEach(node => node.remove()); }
}

export class PokerTable {
    constructor(root, { wallet = new CasinoWallet(root), scene = new PokerScene(root.querySelector('.PokerScene')) } = {}) {
        this.root = root; this.wallet = wallet; this.scene = scene;
        this.data = null; this.timer = null; this.stopped = false; this.inFlight = 0; this.chain = Promise.resolve();
        this.betForm = root.querySelector('.PokerBetForm'); this.chatForm = root.querySelector('.PokerChatForm');
        this.signedIn = root.dataset.signedIn === '1';
        this.messageTable = null; this.lastMessage = 0; this.messageNodes = new Map(); this.lastRender = ''; this.raiseTurn = '';
        this.controllers = new Set();
        this.sound = new PokerSound(root.querySelector('.PokerSoundToggle'));
        this.presentation = new PokerPresentation(root, this.sound);
        this.clock = null;
        this.layoutMedia = window.matchMedia('(max-width: 650px)');
        this.layoutChanged = () => { if (this.data && !this.stopped) { this.lastRender = ''; this.render(); } };
        this.layoutMedia.addEventListener('change', this.layoutChanged);
        this.betForm.elements.amount.addEventListener('input', () => this.betCost());
        FormForm.attach(this.betForm, (_, { signal }) => this.move('raise', signal), { settled: () => this.controls() });
        FormForm.attach(this.chatForm, async (_, { signal, clear }) => {
            const body = this.chatForm.elements.body.value.trim();
            if (!body || !this.data?.table) return;
            const tableId = this.data.table.tableId;
            if (!this.pendingChat || this.pendingChat.body !== body || this.pendingChat.tableId !== tableId) {
                this.pendingChat = { action: 'chat', body, tableId, requestKey: PokerTable.key() };
            }
            if (await this.send(this.pendingChat, signal)) { clear(); this.pendingChat = null; }
        }, { settled: () => this.controls() });
        root.addEventListener('click', event => {
            this.sound.unlock();
            const preset = event.target.closest('[data-poker-preset]');
            if (preset && !preset.disabled) this.preset(preset.dataset.pokerPreset);
            const move = event.target.closest('[data-poker-move]');
            if (move && !move.disabled) FormForm.run(this.betForm, (_, { signal }) => this.move(move.dataset.pokerMove, signal)).catch(() => {});
            const action = event.target.closest('[data-poker-action]');
            if (action && !action.disabled) this.send({ action: action.dataset.pokerAction });
        });
        this.visibility = () => { clearTimeout(this.timer); clearTimeout(this.clock); if (!document.hidden && !this.stopped) { this.tickClock(); this.poll(); } };
        document.addEventListener('visibilitychange', this.visibility);
        window.addEventListener('pagehide', () => this.stop());
        window.addEventListener('pageshow', event => {
            if (event.persisted) { this.stopped = false; document.addEventListener('visibilitychange', this.visibility); this.layoutMedia.addEventListener('change', this.layoutChanged); this.lastRender = ''; this.poll(); }
        });
        this.controls();
    }
    static key() { return Array.from(crypto.getRandomValues(new Uint8Array(16)), byte => byte.toString(16).padStart(2, '0')).join(''); }
    async start() { await this.wallet.start(); if (this.signedIn) await this.poll(); }
    async poll() {
        clearTimeout(this.timer);
        if (this.stopped || document.hidden || !this.signedIn) return;
        try { if (!this.inFlight) await this.send({ action: 'state' }); }
        finally { if (!this.stopped && !document.hidden) this.timer = setTimeout(() => this.poll(), 1500); }
    }
    send(input, signal) {
        this.inFlight++;
        const operation = this.chain.then(async () => {
            if (this.stopped || signal?.aborted) return false;
            const controller = new AbortController();
            const abort = () => controller.abort(); signal?.addEventListener('abort', abort, { once: true });
            this.controllers.add(controller);
            const timer = setTimeout(abort, 12000);
            try {
                const result = await Api.request('/api/poker', input, { signal: controller.signal });
                if (this.stopped || controller.signal.aborted) return false;
                if (!result.ok) { this.root.querySelector('.PokerStatus').textContent = result.error || 'Connection interrupted. Reconnecting…'; return false; }
                this.data = result.data.response; this.wallet.update(this.data.wallet); this.render(); return true;
            } catch (error) {
                if (!this.stopped && !controller.signal.aborted) this.root.querySelector('.PokerStatus').textContent = 'Connection interrupted. Reconnecting…';
                return false;
            } finally { clearTimeout(timer); signal?.removeEventListener('abort', abort); this.controllers.delete(controller); }
        });
        this.chain = operation.catch(() => {});
        return operation.finally(() => { this.inFlight--; this.controls(); });
    }
    async move(move, signal) {
        if (!this.data?.table || !Object.keys(this.data.table.legal).length) return;
        const table = this.data.table;
        let amount = Number(this.betForm.elements.amount.value);
        if (move === 'allin') { amount = table.legal.maxRaiseTo; move = table.legal.canRaise ? 'raise' : 'call'; }
        if (move === 'raise' && !Number.isSafeInteger(amount)) return;
        await this.send({ action: 'act', tableId: table.tableId, version: table.version, move, amount }, signal);
    }
    preset(kind) {
        const table = this.data?.table; if (!table?.legal.canRaise) return;
        const mine = table.seats.find(seat => seat.userId === this.data.userId);
        const called = (mine?.bet || 0) + table.legal.call;
        const amount = kind === 'minimum' ? table.legal.minRaiseTo : called + Math.round((table.pot + table.legal.call) * (kind === 'half' ? .5 : 1));
        this.betForm.elements.amount.value = String(Math.min(table.legal.maxRaiseTo, Math.max(table.legal.minRaiseTo, amount)));
        this.betCost();
    }
    betCost() {
        const table = this.data?.table;
        const mine = table?.seats.find(seat => seat.userId === this.data.userId);
        const amount = Number(this.betForm.elements.amount.value);
        this.root.querySelector('.PokerBetCost').textContent = !this.betForm.elements.amount.disabled && Number.isSafeInteger(amount)
            ? `Raise to ${amount.toLocaleString()} total this round · ${(Math.max(0, amount - (mine?.bet || 0))).toLocaleString()} more chips from your stack.` : '';
    }
    controls() {
        const table = this.data?.table;
        const legal = table?.legal || {};
        const pending = FormForm.isPending(this.betForm) || this.stopped;
        const active = Object.keys(legal).length > 0 && !pending;
        for (const button of this.betForm.querySelectorAll('[data-poker-move]')) {
            const move = button.dataset.pokerMove;
            button.disabled = !active || move === 'check' && !legal.canCheck || move === 'call' && legal.canCheck
                || move === 'allin' && !legal.canRaise && !legal.allInCall;
            if (move === 'call') button.textContent = legal.call ? `Call ${legal.call.toLocaleString()}` : 'Call';
        }
        const amount = this.betForm.elements.amount;
        amount.disabled = !active || !legal.canRaise || legal.minRaiseTo > legal.maxRaiseTo;
        this.betForm.querySelector('[type="submit"]').disabled = amount.disabled;
        amount.min = String(legal.minRaiseTo || 20); amount.max = String(legal.maxRaiseTo || 1000);
        const turnKey = `${table?.tableId}:${table?.version}`;
        if (this.raiseTurn !== turnKey) { amount.value = String(Math.min(legal.minRaiseTo || 40, legal.maxRaiseTo || 1000)); this.raiseTurn = turnKey; }
        this.root.querySelectorAll('[data-poker-preset]').forEach(button => { button.disabled = amount.disabled; });
        this.betCost();
        for (const button of this.root.querySelectorAll('[data-poker-action]')) {
            button.disabled = !this.signedIn || this.inFlight > 0 || this.stopped || (button.dataset.pokerAction === 'join' ? Boolean(this.data?.queued) : !this.data?.queued);
        }
        const chatPending = FormForm.isPending(this.chatForm);
        this.chatForm.elements.body.disabled = !table || chatPending || this.stopped;
        this.chatForm.querySelector('button').disabled = !table || chatPending || this.stopped;
    }
    render() {
        const { table, queued, waiting, matchAt, serverTime, userId } = this.data;
        const inHand = table && table.street !== 'finished';
        this.root.querySelector('.PokerStatus').textContent = inHand
            ? `Table ${table.tableId} · ${queued ? 'Staying for the next hand' : 'Leaving after this hand'}`
            : queued ? `Matching players · ${waiting} waiting · next match in ${Math.max(0, matchAt - serverTime)}s`
                : 'Take a seat with 200–1,000 chips. Your remaining table chips return after every hand.';
        const actor = table?.seats[table.turn];
        const turn = this.root.querySelector('.PokerTurn');
        turn.replaceChildren();
        if (actor) {
            if (actor.userId === userId) turn.append('Your turn');
            else turn.append(new PokerIdentity(actor).nameDOM(), ' is thinking');
            turn.append(` · ${Math.max(0, table.deadline - serverTime)}s`);
        } else turn.append('Waiting for the next hand.');
        const renderKey = `${table?.tableId}:${table?.version}`;
        if (this.lastRender !== renderKey) {
            this.lastRender = renderKey;
            this.renderTable(table, userId); this.scene.update(table);
            this.presentation.update(table, userId);
        }
        this.receivedAt = Date.now(); this.tickClock();
        this.renderChat(table);
        this.controls();
    }
    renderTable(table, viewer) {
        const seats = this.root.querySelector('.PokerSeats'); seats.replaceChildren();
        if (table) {
            const mine = table.seats.findIndex(seat => seat.userId === viewer);
            table.seats.forEach((player, index) => {
                const offset = (index - mine + table.seats.length) % table.seats.length;
                const angle = Math.PI / 2 + offset * Math.PI * 2 / table.seats.length;
                const seat = element('div', 'PokerSeat');
                seat.style.setProperty('--seat-x', `${50 + 39 * Math.cos(angle)}%`);
                seat.style.setProperty('--seat-y', `${50 + 39 * Math.sin(angle) - (offset === 3 || offset === 6 ? 12 : 0)}%`);
                if (this.layoutMedia.matches && table.seats.length === 9) {
                    const positions = [[50, 89], [19, 80], [12, 57], [12, 32], [35, 11], [65, 11], [88, 32], [88, 57], [81, 80]];
                    seat.style.setProperty('--seat-x', positions[offset][0] + '%');
                    seat.style.setProperty('--seat-y', positions[offset][1] + '%');
                }
                seat.classList.toggle('Acting', index === table.turn);
                seat.classList.toggle('Folded', player.folded);
                seat.classList.toggle('Me', player.userId === viewer);
                seat.append(new PokerIdentity(player).toDOM());
                const stack = element('strong', '', `${player.stack.toLocaleString()} chips`);
                if (index === table.dealer) {
                    const dealer = element('span', 'PokerDealer', 'D'); dealer.title = 'Dealer'; dealer.setAttribute('aria-label', 'Dealer'); stack.prepend(dealer);
                }
                seat.append(stack);
                const cards = element('span', 'PokerCards');
                player.cards.forEach(card => cards.append(new PokerCard(card).toDOM())); seat.append(cards);
                seat.append(element('small', '', player.action || 'In The Hand'));
                const clock = element('progress', 'PokerClock'); clock.max = player.userId === null ? 2 : 25;
                clock.hidden = index !== table.turn; clock.setAttribute('aria-label', 'Time remaining to act'); seat.append(clock);
                seats.append(seat);
                if (player.bet > 0 && table.street !== 'finished') {
                    const bet = element('span', 'PokerBet', player.bet.toLocaleString());
                    bet.setAttribute('aria-label', `${player.name} bet ${player.bet} chips this round`);
                    bet.style.left = `${50 + (parseFloat(seat.style.getPropertyValue('--seat-x')) - 50) * .70}%`;
                    bet.style.top = `${50 + (parseFloat(seat.style.getPropertyValue('--seat-y')) - 50) * .63}%`;
                    seats.append(bet);
                }
            });
        }
        this.root.querySelector('.PokerPot').textContent = table ? `POT · ${table.pot.toLocaleString()}` : 'TAKE YOUR SEAT';
        const board = this.root.querySelector('.PokerBoard'); board.replaceChildren();
        table?.board.forEach(card => board.append(new PokerCard(card).toDOM()));
        this.root.querySelector('.PokerStreet').textContent = table ? table.street.toUpperCase() : '';
        const result = this.root.querySelector('.PokerResult');
        result.classList.remove('Arriving'); result.style.removeProperty('animation-delay');
        result.replaceChildren();
        if (table?.street === 'finished') {
            result.append(element('strong', 'PokerShowdownTitle', table.showdown ? 'Showdown' : 'Hand Won Without A Showdown'));
            table.seats.filter(seat => seat.won > 0).forEach((seat, index) => {
                if (index) result.append(' / ');
                result.append(new PokerIdentity(seat).nameDOM(), `: ${seat.won.toLocaleString()} returned from the pot${seat.hand ? ' · ' + seat.hand : ''}`);
            });
            const breakdown = element('div', 'PokerPots');
            let potIndex = 0;
            (table.pots || []).forEach(pot => {
                const row = element('p');
                const title = pot.refund ? 'Uncalled Bet Returned' : potIndex++ === 0 ? 'Main Pot' : `Side Pot ${potIndex - 1}`;
                row.append(`${title} · ${pot.amount.toLocaleString()} chips${pot.winners.length > 1 ? ' · Split' : ''}: `);
                pot.winners.forEach((winner, index) => {
                    if (index) row.append(', ');
                    row.append(new PokerIdentity(table.seats[winner]).nameDOM(), '');
                });
                breakdown.append(row);
            });
            const mine = table.seats.find(seat => seat.userId === viewer);
            if (mine) {
                const net = mine.won - (mine.total || 0);
                breakdown.append(element('p', 'PokerNet', `Your hand: ${net >= 0 ? '+' : '−'}${Math.abs(net).toLocaleString()} chips net · ${mine.stack.toLocaleString()} returned to your wallet.`));
            }
            const winners = table.seats.filter((seat, index) => seat.bestCards && (table.pots || []).some(pot => !pot.refund && pot.winners.includes(index)));
            winners.forEach(player => {
                const button = element('button', 'PokerWinningHand', `Show ${player.name}’s ${player.hand}`);
                button.type = 'button';
                button.addEventListener('click', () => this.highlightHand(table, player));
                breakdown.append(button);
            });
            result.append(breakdown);
            if (winners.length) this.highlightHand(table, winners[0]);
        }
        const log = this.root.querySelector('.PokerLog'); log.replaceChildren();
        table?.log.forEach(entry => {
            const row = element('li');
            const player = table.seats.find(seat => entry.startsWith(seat.name + ': '));
            if (player) row.append(new PokerIdentity(player).nameDOM(), entry.slice(player.name.length));
            else row.append(entry);
            log.append(row);
        });
        log.scrollTop = log.scrollHeight;
        this.renderActions(table);
    }
    highlightHand(table, player) {
        const winning = new Set(player.bestCards);
        const seats = this.root.querySelectorAll('.PokerSeat');
        table.seats.forEach((seat, index) => seats[index].querySelectorAll('.PokerCard').forEach((card, i) => {
            card.classList.toggle('Winning', seat === player && winning.has(seat.cards[i]));
        }));
        this.root.querySelectorAll('.PokerBoard .PokerCard').forEach((card, index) => card.classList.toggle('Winning', winning.has(table.board[index])));
        this.root.querySelectorAll('.PokerWinningHand').forEach(button => button.setAttribute('aria-pressed', String(button.textContent === `Show ${player.name}’s ${player.hand}`)));
    }
    tickClock() {
        clearTimeout(this.clock);
        if (this.stopped || !this.data) return;
        const { table, serverTime, queued, matchAt, waiting } = this.data;
        const now = serverTime + (Date.now() - this.receivedAt) / 1000;
        this.root.querySelectorAll('.PokerClock').forEach(clock => {
            clock.value = Math.max(0, (table?.deadline || 0) - now);
            clock.classList.toggle('Urgent', clock.value <= 5 && clock.max === 25);
        });
        const label = this.root.querySelector('.PokerWaiting');
        label.textContent = !table || table.street === 'finished'
            ? queued ? `${waiting} waiting · next table in ${Math.max(0, Math.ceil(matchAt - now))}s` : 'Take a seat when you’re ready.' : '';
        if (!document.hidden) this.clock = setTimeout(() => this.tickClock(), 250);
    }
    renderActions(table) {
        const sameTable = table && this.actionTable === table.tableId;
        if (!sameTable) this.root.querySelectorAll('.PokerAction').forEach(node => node.remove());
        const count = sameTable ? Math.max(0, table.version - this.actionVersion) : 0;
        this.actionTable = table?.tableId;
        this.actionVersion = table?.version;
        if (!count) return;
        const seats = this.root.querySelectorAll('.PokerSeat');
        table.log.slice(-count).forEach(entry => {
            const index = table.seats.findIndex(player => entry.startsWith(player.name + ': '));
            if (index < 0) return;
            const label = entry.slice(table.seats[index].name.length + 2);
            const action = element('span', 'PokerAction', label);
            const kind = label.includes('All In') ? 'AllIn' : label.split(' ')[0];
            if (['Fold', 'Check', 'Call', 'Raise', 'AllIn'].includes(kind)) action.classList.add(kind);
            action.setAttribute('aria-hidden', 'true');
            action.style.left = seats[index].style.getPropertyValue('--seat-x');
            action.style.top = seats[index].style.getPropertyValue('--seat-y');
            action.addEventListener('animationend', () => action.remove(), { once: true });
            this.root.querySelector('.PokerTable').append(action);
        });
    }
    renderChat(table) {
        const messages = this.root.querySelector('.PokerMessages');
        if (this.messageTable !== table?.tableId) {
            messages.replaceChildren(); this.lastMessage = 0; this.messageNodes.clear(); this.messageTable = table?.tableId;
        }
        const nearBottom = messages.scrollHeight - messages.scrollTop - messages.clientHeight < 50;
        const visible = new Set((table?.chat || []).map(message => message.messageId));
        for (const [id, node] of this.messageNodes) {
            if (!visible.has(id)) { node.remove(); this.messageNodes.delete(id); }
        }
        for (const message of table?.chat || []) {
            if (message.messageId <= this.lastMessage) continue;
            const row = element('div', 'PokerMessage');
            row.append(new PokerIdentity(message).toDOM(), element('p', '', message.body)); messages.append(row);
            this.messageNodes.set(message.messageId, row);
            this.lastMessage = message.messageId;
        }
        while (messages.childElementCount > 50) messages.firstElementChild.remove();
        if (nearBottom) messages.scrollTop = messages.scrollHeight;
    }
    stop() {
        this.stopped = true; clearTimeout(this.timer); clearTimeout(this.clock); this.controllers.forEach(controller => controller.abort());
        this.presentation.clear(); this.sound.stop();
        this.layoutMedia.removeEventListener('change', this.layoutChanged);
        this.root.querySelectorAll('.PokerAction').forEach(node => node.remove());
        FormForm.cancel(this.betForm); FormForm.cancel(this.chatForm); document.removeEventListener('visibilitychange', this.visibility);
    }
}

ReadyHandler.add(() => {
    const root = document.querySelector('.PokerGame');
    if (!root) return;
    const game = new PokerTable(root); game.start();
    window.addEventListener('pagehide', event => { if (!event.persisted) game.scene.dispose(); });
});
