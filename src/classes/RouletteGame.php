<?php

declare(strict_types=1);

class RouletteGame extends GameRoom
{
    public function toDOM(): \DOMElement
    {
        // This game supplies the room's contents rather than the lobby cards.
        $this -> markRendered();
        $root = $this -> element('section', ['class' => 'GameRoom RouletteGame', 'data-roulette' => '',
            'data-positions' => json_encode(Roulette::positions(), JSON_THROW_ON_ERROR),
            'data-wheel' => json_encode(Roulette::WHEEL, JSON_THROW_ON_ERROR)]);
        $this -> chrome($root);
        $heading = $this -> element('div', ['class' => 'roulette-heading']);
        $heading -> appendChild($this -> element('div', ['class' => 'casino-eyebrow'], 'THE CLASSICS / 01'));
        $heading -> appendChild($this -> element('h1', ['class' => 'casino-title'], 'European roulette'));
        $heading -> appendChild($this -> element('p', [], 'A turn of the wheel. A moment of possibility.'));
        $root -> appendChild($heading);
        $layout = $this -> element('div', ['class' => 'roulette-layout']);
        $stage = $this -> element('div', ['class' => 'roulette-stage']);
        $stage -> appendChild($this -> element('div', ['class' => 'roulette-stage-label'], 'SINGLE ZERO • 37 POCKETS'));
        $scene = $this -> element('div', ['class' => 'roulette-scene', 'data-scene' => '', 'aria-label' => 'Animated roulette wheel']);
        $scene -> appendChild($this -> element('div', ['class' => 'wheel-fallback', 'aria-hidden' => 'true', 'hidden' => ''], '0'));
        $stage -> appendChild($scene);
        $stage -> appendChild($this -> element('p', ['class' => 'roulette-result', 'data-result' => '', 'role' => 'status', 'aria-live' => 'polite'], 'Place your chips. The table is yours.'));
        $stage -> appendChild($this -> element('div', ['class' => 'roulette-history', 'data-history' => '', 'aria-label' => 'Recent results']));
        $layout -> appendChild($stage);

        $form = new FormForm();
        $form -> class .= ' RouletteForm';
        $form -> attributes['aria-label'] = 'Roulette bets';
        $form -> attributes['data-bet-form'] = '';
        $form_element = $form -> toDOM();
        $fieldset = $this -> element('fieldset', ['data-controls' => '']);
        $fieldset -> appendChild($this -> element('legend', [], 'PLACE YOUR BETS'));
        $play = $this -> element('div', ['class' => 'roulette-play-controls']);
        $play -> appendChild($this -> element('button', ['type' => 'submit', 'class' => 'roulette-spin', 'data-spin' => '', 'disabled' => 'disabled'], 'SPIN THE WHEEL'));
        $form_element -> appendChild($play);
        $chips = $this -> element('div', ['class' => 'roulette-chips', 'role' => 'group', 'aria-label' => 'Chip denomination']);
        $chips -> appendChild($this -> element('span', ['class' => 'roulette-total', 'data-total' => ''], 'Total: 0 chips'));
        foreach ([1, 10, 25, 100, 500] as $amount) {
            $chips -> appendChild($this -> element('button', ['type' => 'button', 'data-chip' => $amount, 'class' => 'casino-chip chip-' . $amount, 'aria-pressed' => $amount === 25 ? 'true' : 'false', 'aria-label' => $amount . ' chips'], (string) $amount));
        }
        $chips -> appendChild($this -> element('button', ['type' => 'button', 'class' => 'roulette-clear', 'data-clear' => ''], 'Clear Bets'));
        $fieldset -> appendChild($chips);
        $board = $this -> element('div', ['class' => 'roulette-board', 'aria-label' => 'Betting table']);
        foreach (range(0, 36) as $n) {
            $button = $this -> element('button', ['type' => 'button', 'class' => 'bet-number ' . Roulette::color($n), 'data-bet' => 'straight:' . $n, 'aria-label' => $n . ', ' . Roulette::color($n) . ', pays 35 to 1'], (string) $n);
            $board -> appendChild($button);
        }
        $fieldset -> appendChild($board);
        $outside = $this -> element('div', ['class' => 'roulette-outside']);
        foreach (['dozen:1', 'dozen:2', 'dozen:3', 'column:1', 'column:2', 'column:3', 'low', 'even', 'red', 'black', 'odd', 'high'] as $key) {
            $position = Roulette::positions()[$key];
            $outside -> appendChild($this -> element('button', ['type' => 'button', 'data-bet' => $key, 'class' => 'bet-outside ' . $key, 'aria-label' => $position['label'] . ', pays ' . $position['odds'] . ' to 1'], $position['label']));
        }
        $fieldset -> appendChild($outside);
        $advanced = $this -> element('details', ['class' => 'roulette-advanced']);
        $advanced -> appendChild($this -> element('summary', [], 'Splits, streets & other inside bets'));
        $advanced -> appendChild($this -> element('label', ['for' => 'inside-position'], 'Choose an inside bet'));
        $select = $this -> element('select', ['id' => 'inside-position']);
        foreach (Roulette::positions() as $key => $position) {
            if (str_starts_with($key, 'straight:') || in_array($position['odds'], [1, 2], true)) continue;
            $select -> appendChild($this -> element('option', ['value' => $key], $position['label'] . ' · ' . $position['odds'] . ':1'));
        }
        $advanced -> appendChild($select);
        $advanced -> appendChild($this -> element('button', ['type' => 'button', 'data-add-inside' => ''], 'Add selected chip'));
        $fieldset -> appendChild($advanced);
        $fieldset -> appendChild($this -> element('ul', ['class' => 'roulette-slip', 'data-slip' => '', 'aria-label' => 'Your bets']));
        $fieldset -> appendChild($this -> element('small', ['class' => 'roulette-limit'], '1 chip minimum · 10,000 chips maximum per spin'));
        $form_element -> appendChild($fieldset);
        $layout -> appendChild($form_element);
        $root -> appendChild($layout);
        $rules = $this -> element('details', ['class' => 'roulette-rules']);
        $rules -> appendChild($this -> element('summary', [], 'The odds, exactly as at a European table'));
        $rules -> appendChild($this -> element('p', [], 'Each pocket, 0–36, has a 1 in 37 chance on every spin. Previous results never change the odds. Zero loses for red/black, odd/even, low/high, dozens and columns. No la partage or en prison.'));
        $rules -> appendChild($this -> element('p', [], 'Profit payouts: straight 35:1; split 17:1; street or zero trio 11:1; corner or first four 8:1; six line 5:1; dozen or column 2:1; red/black, odd/even and low/high 1:1. A win also returns the winning stake. Every bet has a 1/37 house edge (about 2.70%).'));
        $rules -> appendChild($this -> element('p', [], 'You receive 1,000 chips on your first visit, then once an hour while this room is visible. If you leave, your next visit grants at most 1,000 and starts the next hour. Your remaining chips and winnings stay yours.'));
        $root -> appendChild($rules);
        $this -> footer($root);
        return $root;
    }
}
