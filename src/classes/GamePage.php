<?php

declare(strict_types=1);

class GamePage extends Page
{
    public string $game = 'index';

    public function send(): void
    {
        SecurityHeaders::send(allows_game_ads: true);
        parent::send();
    }

    public function toDOM(): \DOMElement
    {
        if (!in_array($this -> game, ['index', 'roulette', 'poker', 'vlt', 'singularity', 'pachinko'], true)) throw new \LogicException('Unknown game page.');
        $this -> bodyClass = 'CasinoPage';
        $this -> addHeadContent(new Link(['rel' => 'stylesheet', 'href' => '/games/casino.css']));
        if (in_array($this -> game, ['singularity', 'pachinko'], true)) $this -> addHeadContent(new Link(['rel' => 'stylesheet', 'href' => '/games/vlt.css']));
        $this -> addHeadContent(new Link(['rel' => 'stylesheet', 'href' => '/games/' . $this -> game . '.css']));
        $this -> addHeadContent(new ModuleScript(['src' => '/games/casino.js']));
        $this -> addHeadContent(new ModuleScript(['src' => '/games/' . $this -> game . '.js']));
        return parent::toDOM();
    }
}
