import { Api, ReadyHandler } from '/scripts/Runtime.js';

/** One iframe loads the creative that fits the current screen width. */
export class CasinoAdvertisement {
    constructor(frame) {
        this.frame = frame;
        this.media = window.matchMedia('(min-width: 940px)');
        this.tablet = window.matchMedia('(min-width: 768px)');
        this.update = () => {
            const size = this.media.matches ? 'desktop' : this.tablet.matches ? 'tablet' : 'mobile';
            const source = this.frame.dataset[size + 'Ad'];
            this.frame.width = size === 'desktop' ? '900' : size === 'tablet' ? '728' : '300';
            this.frame.height = size === 'tablet' ? '90' : '250';
            if (this.frame.getAttribute('src') !== source) this.frame.src = source;
        };
        this.media.addEventListener('change', this.update);
        this.tablet.addEventListener('change', this.update);
        this.update();
    }
}

ReadyHandler.add(() => {
    document.querySelectorAll('[data-desktop-ad]').forEach(frame => new CasinoAdvertisement(frame));
});

/** One shared balance and one completion-scheduled visitor heartbeat. */
export class CasinoWallet {
    constructor(root) {
        this.root = root;
        this.data = null;
        this.timer = null;
        this.request = null;
        this.paused = false;
        this.stopped = false;
        this.ticking = false;
        this.lastRefresh = 0;
        this.clockOffset = 0;
        this.listeners = new Set();
        this.visibility = () => { clearTimeout(this.timer); if (!document.hidden) this.tick(); };
        document.addEventListener('visibilitychange', this.visibility);
        window.addEventListener('pagehide', () => this.stop());
        window.addEventListener('pageshow', event => {
            if (!event.persisted) return;
            this.stopped = false;
            this.paused = false;
            document.addEventListener('visibilitychange', this.visibility);
            this.tick();
        });
    }

    async start() { await this.tick(); }

    update(data) {
        this.data = data;
        this.clockOffset = data.serverTime * 1000 - Date.now();
        this.lastRefresh = Date.now();
        this.root.querySelector('[data-balance]').textContent = data.balance.toLocaleString();
        this.root.querySelector('[data-guest-message]').hidden = !data.guest;
        this.root.querySelector('[data-wallet-status]').textContent = data.granted
            ? '+1,000 chips, on the house. Enjoy your game.' : '';
        this.countdown();
        this.listeners.forEach(listener => listener(data));
    }

    countdown() {
        if (!this.data) return;
        const seconds = Math.max(0, Math.ceil((this.data.nextGrantAt * 1000 - Date.now() - this.clockOffset) / 1000));
        this.root.querySelector('[data-refill]').textContent = seconds > 0
            ? `+1,000 in ${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`
            : 'Your next 1,000 chips are ready';
    }

    async refresh() {
        if (this.request || this.paused || this.stopped || document.hidden) return;
        const controller = new AbortController();
        this.request = controller;
        const timeout = setTimeout(() => controller.abort(), 15000);
        try {
            const result = await Api.request('/api/game-wallet', {}, { signal: controller.signal });
            if (result.ok && !controller.signal.aborted && !this.stopped && !this.paused) this.update(result.data.response);
            else if (!this.paused) this.root.querySelector('[data-wallet-status]').textContent = result.error || 'Connecting to your chips…';
        } finally {
            clearTimeout(timeout);
            if (this.request === controller) this.request = null;
            this.lastRefresh = Date.now();
        }
    }

    async tick() {
        clearTimeout(this.timer);
        if (document.hidden || this.stopped || this.ticking) return;
        this.ticking = true;
        try {
            this.countdown();
            if (!this.paused && (this.lastRefresh === 0 || Date.now() - this.lastRefresh >= 30000
                || this.data && Date.now() + this.clockOffset >= this.data.nextGrantAt * 1000 && Date.now() - this.lastRefresh >= 2000)) {
                await this.refresh();
            }
        } finally {
            this.ticking = false;
            if (!this.stopped && !document.hidden) this.timer = setTimeout(() => this.tick(), 1000);
        }
    }

    pause() { this.paused = true; this.request?.abort(); }
    resume() { this.paused = false; this.tick(); }
    stop() { this.stopped = true; clearTimeout(this.timer); this.paused = true; this.request?.abort(); document.removeEventListener('visibilitychange', this.visibility); }
}
