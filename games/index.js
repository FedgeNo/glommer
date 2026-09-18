import { ReadyHandler } from '/scripts/Runtime.js';
import { CasinoWallet } from '/games/casino.js';

ReadyHandler.add(() => {
    const root = document.querySelector('.GameRoom');
    if (root) new CasinoWallet(root).start();
});
