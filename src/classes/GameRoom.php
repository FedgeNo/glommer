<?php

declare(strict_types=1);

/** Shared casino chrome; individual games own their rules, controls, and scene. */
class GameRoom extends Section
{
    public ?string $class = 'GameRoom';

    protected function element(string $tag, array $attributes = [], ?string $text = null): \DOMElement
    {
        $element = self::$document -> createElement($tag);
        foreach ($attributes as $key => $value) $element -> setAttribute($key, (string) $value);
        if ($text !== null) $element -> appendChild(self::$document -> createTextNode($text));
        return $element;
    }

    protected function chrome(\DOMElement $root): void
    {
        $bar = $this -> element('header', ['class' => 'casino-header']);
        $brand = $this -> element('a', ['href' => '/games/', 'class' => 'casino-brand'], 'GLOMMER');
        $brand -> appendChild($this -> element('span', [], 'THE GAMES ROOM'));
        $bar -> appendChild($brand);
        $wallet = $this -> element('div', ['class' => 'casino-wallet', 'aria-label' => 'Your chips']);
        $wallet -> appendChild($this -> element('span', ['class' => 'chip-symbol', 'aria-hidden' => 'true'], 'G'));
        $wallet -> appendChild($this -> element('strong', ['data-balance' => ''], '…'));
        $wallet -> appendChild($this -> element('span', [], 'CHIPS'));
        $wallet -> appendChild($this -> element('small', ['data-refill' => ''], '1,000 free chips every hour you’re here'));
        $bar -> appendChild($wallet);
        $root -> appendChild($bar);
        $status = $this -> element('p', ['class' => 'casino-status', 'data-wallet-status' => '', 'role' => 'status']);
        $root -> appendChild($status);
    }

    protected function footer(\DOMElement $root): void
    {
        $save = $this -> element('aside', ['class' => 'casino-save', 'data-guest-message' => '']);
        $save -> appendChild($this -> element('strong', [], 'Playing as a guest? Keep your winnings.'));
        $save -> appendChild($this -> element('span', [], 'Create an account to save your chips across devices.'));
        $save -> appendChild($this -> element('a', ['href' => '/signup'], 'Save my chips →'));
        $save -> appendChild($this -> element('a', ['href' => '/login'], 'Sign in'));
        $root -> appendChild($save);
        $root -> appendChild($this -> advertisementDOM());
        $root -> appendChild($this -> element('p', ['class' => 'casino-note'], 'Just for fun. Chips have no cash value and cannot be bought, redeemed, or exchanged for prizes. Missed hourly grants do not accumulate.'));
    }

    protected function advertisementDOM(): \DOMElement
    {
        $ad = $this -> element('aside', ['class' => 'casino-ad', 'aria-label' => 'Advertisement']);
        $ad -> appendChild($this -> element('iframe', [
            'data-desktop-ad' => 'https://a.magsrv.com/iframe.php?idzone=6032896&size=900x250',
            'data-mobile-ad' => 'https://a.magsrv.com/iframe.php?idzone=6032898&size=300x250',
            'data-tablet-ad' => 'https://a.magsrv.com/iframe.php?idzone=6032906&size=728x90',
            'width' => '300', 'height' => '250', 'title' => 'Advertisement supporting the games room',
            'loading' => 'lazy', 'referrerpolicy' => 'strict-origin-when-cross-origin',
        ]));
        return $ad;
    }

    public function toDOM(): \DOMElement
    {
        $root = parent::toDOM();
        $this -> chrome($root);
        $root -> appendChild($this -> element('p', ['class' => 'casino-eyebrow'], 'TAKE A SEAT. STAY A WHILE.'));
        $root -> appendChild($this -> element('h1', ['class' => 'casino-title'], 'A little play. A little luck.'));
        $root -> appendChild($this -> element('p', ['class' => 'casino-intro'], 'Your first 1,000 chips are on the house. No account needed.'));
        $cards = $this -> element('div', ['class' => 'casino-game-grid']);
        $card = $this -> element('a', ['class' => 'casino-game-card RouletteLobbyCard', 'href' => '/games/roulette']);
        $card -> appendChild($this -> element('small', [], 'CASINO · SINGLE ZERO'));
        $card -> appendChild($this -> element('h2', [], 'European roulette'));
        $card -> appendChild($this -> element('p', [], '37 pockets. Classic bets. A seat at the wheel.'));
        $card -> appendChild($this -> element('span', ['class' => 'casino-enter'], 'Play roulette →'));
        $cards -> appendChild($card);
        $poker = $this -> element('a', ['class' => 'casino-game-card PokerLobbyCard', 'href' => '/games/poker']);
        $poker -> appendChild($this -> element('small', [], 'CASINO · PEOPLE + BOTS'));
        $poker -> appendChild($this -> element('h2', [], 'Texas Hold’em'));
        $poker -> appendChild($this -> element('p', [], 'Nine seats. Real opponents. Take your chips to the table.'));
        $poker -> appendChild($this -> element('span', ['class' => 'casino-enter'], 'Take a seat →'));
        $cards -> appendChild($poker);
        $cards -> appendChild($this -> advertisementDOM());
        $vlt = $this -> element('a', ['class' => 'casino-game-card VLTLobbyCard', 'href' => '/games/vlt']);
        $vlt -> appendChild($this -> element('small', [], 'VLT · FIVE REELS / TEN LINES'));
        $vlt -> appendChild($this -> element('h2', [], 'Nova Vault'));
        $vlt -> appendChild($this -> element('p', [], 'Cosmic reels, luminous wins, and Supernova multipliers.'));
        $vlt -> appendChild($this -> element('span', ['class' => 'casino-enter'], 'Enter the vault →'));
        $cards -> appendChild($vlt);
        $singularity = $this -> element('a', ['class' => 'casino-game-card SingularityLobbyCard', 'href' => '/games/singularity']);
        $singularity -> appendChild($this -> element('small', [], 'CLUSTER CASCADES · CHAIN REACTIONS'));
        $singularity -> appendChild($this -> element('h2', [], 'Singularity'));
        $singularity -> appendChild($this -> element('p', [], 'Connect symbols. Detonate clusters. Escalate the multiplier with every chain reaction.'));
        $singularity -> appendChild($this -> element('span', ['class' => 'casino-enter'], 'Ignite the reactor →'));
        $cards -> appendChild($singularity);
        $cards -> appendChild($this -> advertisementDOM());
        $pachinko = $this -> element('a', ['class' => 'casino-game-card PachinkoLobbyCard', 'href' => '/games/pachinko']);
        $pachinko -> appendChild($this -> element('small', [], 'PACHINKO · CHROME + NEON'));
        $pachinko -> appendChild($this -> element('h2', [], 'Neon Pachinko'));
        $pachinko -> appendChild($this -> element('p', [], 'Launch a chrome ball through a constellation of neon pins. Chase the 50× pockets.'));
        $pachinko -> appendChild($this -> element('span', ['class' => 'casino-enter'], 'Launch a ball →'));
        $cards -> appendChild($pachinko);
        $derby = $this -> element('a', ['class' => 'casino-game-card DerbyLobbyCard', 'href' => '/games/derby']);
        $derby -> appendChild($this -> element('small', [], 'ARCADE · 500 CHIPS PER LIFE'));
        $derby -> appendChild($this -> element('h2', [], 'Bent Metal Derby'));
        $derby -> appendChild($this -> element('p', [], 'Race, drift, and fight for the podium. Your garage uses the same chips.'));
        $derby -> appendChild($this -> element('span', ['class' => 'casino-enter'], 'Start your engine →'));
        $cards -> appendChild($derby);
        $root -> appendChild($cards);
        $this -> footer($root);
        return $root;
    }
}
