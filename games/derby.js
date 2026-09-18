/* Loaded after the standalone game's script. Only the economy seam is replaced. */
class GlommerDerbyAdapter {
    constructor() {
        this.ready = false;
        this.stopped = false;
        this.busy = false;
        this.race = null;
        this.wallet = null;
        this.timer = null;
        this.pendingKey = 'glommer.derby.pending';
        this.pending = null;
        this.complete = null;
        this.controls();
        Game.cash = 0;
        for (const vehicle of PLAYER_TYPES) for (const upgrade of UPGRADES) Game.upgrades[vehicle][upgrade.key] = 0;
        // Life assignments in the free game reset its run; the hosted game has
        // no separate life balance to reset or spend a second time.
        Object.defineProperty(Game, 'lives', {get: () => Math.floor(Game.cash / 500), set() {}});
        Economy.format = amount => amount.toLocaleString() + ' chips';
        Economy.livesText = () => Math.floor(Game.cash / 500).toLocaleString() + ' lives';
        Economy.transact = (action, done) => this.transact(action, done);
        const originalWin = showWin, originalLose = showLose;
        showWin = () => { originalWin(); this.resultSummary($('winBoard')); };
        showLose = () => { originalLose(); this.resultSummary($('loseBoard')); };
        refreshCashTags();
        $('resetCareerBtn').hidden = true;
        $('startBtn').textContent = 'START ENGINE · 500 CHIPS';
        for (const id of ['startCash', 'garageCash']) {
            const node = $(id).parentNode.firstChild;
            if (node?.nodeType === 3) node.textContent = 'CHIPS ';
        }
        window.addEventListener('pagehide', () => {
            this.stopped = true; clearTimeout(this.timer); this.controller?.abort();
        });
        window.addEventListener('pageshow', event => {
            if (event.persisted) { this.stopped = false; this.pending ? this.send() : this.refresh(); }
        });
        document.addEventListener('visibilitychange', () => {
            clearTimeout(this.timer);
            if (!document.hidden && !this.stopped) this.refresh();
        });
        try { this.pending = JSON.parse(sessionStorage.getItem(this.pendingKey)); }
        catch { this.status.textContent = 'Browser storage is unavailable. Transactions need storage to recover safely.'; return; }
        if (this.pending) {
            Economy.pending = true;
            this.complete = accepted => {
                Economy.pending = false;
                if (accepted && this.pending?.action.type === 'race') this.beginRecoveredRace(this.pending.action);
            };
            this.send();
        } else this.refresh();
    }

    controls() {
        const bar = document.createElement('nav');
        bar.className = 'glommer-derby-bar';
        const link = (text, href) => {
            const a = document.createElement('a'); a.textContent = text; a.href = href; bar.append(a); return a;
        };
        link('← Games', '/games/');
        this.balance = document.createElement('strong'); bar.append(this.balance);
        this.status = document.createElement('span'); this.status.className = 'glommer-derby-status';
        this.status.setAttribute('role', 'status'); bar.append(this.status);
        this.retry = document.createElement('button'); this.retry.type = 'button'; this.retry.textContent = 'Retry connection';
        this.retry.hidden = true; this.retry.addEventListener('click', () => this.pending ? this.send() : this.refresh()); bar.append(this.retry);
        document.body.append(bar);
        const note = document.createElement('p'); note.className = 'glommer-derby-note';
        note.textContent = '1 life = 500 chips. Every start or retry costs one life. Winning a life adds 500 chips alongside your other rewards. Chips have no cash value. Your next 1,000 chips arrive hourly while you’re here; missed grants do not accumulate.';
        $('startScreen').append(note);
        this.advertisement();
        // Keep host chrome out of the driving view; show it on menus and results.
        const observer = new MutationObserver(() => {
            document.body.toggleAttribute('data-derby-playing', ['playing', 'countdown', 'loading'].includes(Game.state));
        });
        for (const id of ['startScreen', 'winScreen', 'loseScreen', 'garageScreen', 'pauseScreen', 'loadingScreen']) {
            const element = $(id); if (element) observer.observe(element, {attributes: true, attributeFilter: ['class']});
        }
    }

    advertisement() {
        const host = document.createElement('aside'); host.className = 'glommer-derby-ad';
        const frame = document.createElement('iframe'); frame.title = 'Advertisement supporting the games room';
        frame.loading = 'lazy'; frame.referrerPolicy = 'strict-origin-when-cross-origin'; host.append(frame); $('startScreen').append(host);
        const tablet = matchMedia('(min-width: 768px)');
        const update = () => {
            const [zone, width, height] = tablet.matches ? [6032906, 728, 90] : [6032898, 300, 250];
            const src = `https://a.magsrv.com/iframe.php?idzone=${zone}&size=${width}x${height}`;
            frame.width = width; frame.height = height;
            if (frame.getAttribute('src') !== src) frame.src = src;
        };
        tablet.addEventListener('change', update); update();
    }

    async request(payload) {
        this.controller = new AbortController();
        const timer = setTimeout(() => this.controller?.abort(), 20000);
        try {
            const response = await fetch('/api/derby', {
                method: 'POST', credentials: 'same-origin', signal: this.controller.signal,
                headers: {'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="glommer-csrf"]').content},
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (!response.ok) {
                const error = new Error(data.error || 'Unable to update your chips.');
                error.definitive = [409, 422].includes(response.status); throw error;
            }
            return data.response;
        } finally { clearTimeout(timer); this.controller = null; }
    }

    apply(data) {
        this.wallet = data.wallet;
        Game.cash = data.wallet.balance;
        Game.upgrades = data.upgrades;
        this.balance.textContent = Economy.format(Game.cash);
        refreshCashTags();
        if (!$('garageScreen').classList.contains('hidden')) renderGarage();
        if (!$('loseScreen').classList.contains('hidden')) showLose();
        this.ready = true;
    }

    async refresh() {
        clearTimeout(this.timer);
        if (this.stopped || document.hidden || this.busy || this.pending) return;
        this.busy = true;
        try {
            const data = await this.request({action: 'state'});
            if (this.stopped) return;
            this.apply(data); this.retry.hidden = true;
            const seconds = Math.max(0, data.wallet.nextGrantAt - data.wallet.serverTime);
            this.status.textContent = `1 life = 500 chips · next 1,000 in ${Math.ceil(seconds / 60)} min`;
        } catch (error) {
            if (!this.stopped) { this.status.textContent = error.message; this.retry.hidden = false; }
        } finally {
            this.busy = false;
            if (this.queued && !this.stopped) {
                const {action, done} = this.queued; this.queued = null;
                this.transact(action, done); return;
            }
            if (!this.stopped && !document.hidden) this.timer = setTimeout(() => this.refresh(), 15000);
        }
    }

    transact(action, done) {
        if (this.busy && !this.pending && this.ready) { this.queued = {action, done}; return; }
        if (!this.ready || this.pending) {
            this.status.textContent = 'Wait for your chip balance to finish updating.'; done(false); return;
        }
        let payload;
        if (action.type === 'reward') {
            payload = {action: 'reward', requestKey: this.race, result: action.result};
        } else {
            const key = [...crypto.getRandomValues(new Uint8Array(16))].map(n => n.toString(16).padStart(2, '0')).join('');
            payload = action.type === 'race'
                ? {action: 'race', requestKey: key, mode: Game.raceFormat}
                : {action: 'upgrade', requestKey: key, vehicle: action.vehicle, upgrade: action.key, level: Game.upgrades[action.vehicle][action.key]};
        }
        const pending = {payload, action};
        try { sessionStorage.setItem(this.pendingKey, JSON.stringify(pending)); }
        catch { this.status.textContent = 'Enable browser storage before spending chips.'; done(false); return; }
        this.pending = pending; this.complete = done;
        this.send();
    }

    beginRecoveredRace(action) {
        beginRound(action.round, action.fresh, action.spendLife);
    }

    resultSummary(board) {
        board.querySelector('[data-career-reward]')?.remove();
        board.querySelector('.glommer-derby-result')?.remove();
        const section = document.createElement('section'); section.className = 'glommer-derby-result';
        section.setAttribute('aria-label', 'Chips for this race');
        const heading = document.createElement('h3'); heading.textContent = 'CHIPS THIS RACE'; section.append(heading);
        const row = (label, value) => {
            const line = document.createElement('div'); line.className = 'line';
            const name = document.createElement('span'); name.textContent = label;
            const amount = document.createElement('strong'); amount.textContent = value;
            line.append(name, amount); section.append(line);
        };
        row('Entry · 1 life', '−500 chips');
        const breakdown = Game.lastBreakdown;
        const awaiting = Game.eventType !== 'trial' && breakdown && !breakdown.raceLoss && !this.settlement;
        if (awaiting) {
            row('Winnings', 'Awaiting confirmation');
        } else {
            const payout = this.settlement?.payout || 0;
            const life = breakdown?.freeLife && payout > 0 ? 500 : 0;
            row('Race, combat and drift', '+' + Economy.format(payout - life));
            if (life) row('Life won', '+500 chips');
            row('Total winnings', '+' + Economy.format(payout));
            const net = payout - 500;
            row('Net after entry', (net >= 0 ? '+' : '−') + Economy.format(Math.abs(net)));
        }
        board.prepend(section);
    }

    setPending(pending) {
        if (pending) {
            if (!this.disabled) {
                this.disabled = new Map();
                for (const control of document.querySelectorAll('.overlay button, .overlay input, .overlay select')) {
                    this.disabled.set(control, control.disabled); control.disabled = true;
                }
            }
            document.body.setAttribute('data-derby-pending', '');
        } else {
            for (const [control, disabled] of this.disabled || []) control.disabled = disabled;
            this.disabled = null;
            document.body.removeAttribute('data-derby-pending');
        }
    }

    async send() {
        clearTimeout(this.timer);
        if (this.busy || !this.pending || this.stopped) return;
        this.busy = true; this.retry.hidden = true;
        this.setPending(true);
        this.status.textContent = 'Updating chips…';
        try {
            const data = await this.request(this.pending.payload);
            if (this.stopped) return;
            this.apply(data);
            if (this.pending.action.type === 'race') { this.race = this.pending.payload.requestKey; this.settlement = null; }
            if (this.pending.action.type === 'reward') {
                this.settlement = data.round;
                if (!$('winScreen').classList.contains('hidden')) this.resultSummary($('winBoard'));
                if (!$('loseScreen').classList.contains('hidden')) this.resultSummary($('loseBoard'));
            }
            sessionStorage.removeItem(this.pendingKey);
            this.complete?.(true);
            this.pending = null; this.complete = null;
            this.status.textContent = '1 life = 500 chips';
        } catch (error) {
            if (this.stopped) return;
            this.status.textContent = error.message;
            if (error.definitive) {
                sessionStorage.removeItem(this.pendingKey);
                this.complete?.(false); this.pending = null; this.complete = null;
            } else this.retry.hidden = false;
        } finally {
            this.busy = false;
            if (!this.pending) this.setPending(false);
            if (!this.stopped && !this.pending) this.timer = setTimeout(() => this.refresh(), 15000);
        }
    }
}

new GlommerDerbyAdapter();
