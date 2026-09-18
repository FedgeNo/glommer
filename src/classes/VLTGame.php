<?php

declare(strict_types=1);

class VLTGame extends GameRoom
{
    protected const RULES = VLT::class;
    protected const ROWS = 3;
    protected const METER = '10 LINES · ALL ACTIVE';
    protected const PRESENTATION = ['name' => 'Nova Vault', 'eyebrow' => 'COSMIC REELS / LIMITLESS LIGHT',
        'tagline' => 'Five reels. Ten lines. One cosmic machine.', 'feature' => 'Supernova',
        'welcome' => 'ENTER THE VAULT', 'spin' => 'SPIN THE VAULT', 'pending' => 'OPENING THE VAULT…',
        'charging' => 'CHARGING THE VAULT', 'contact' => 'Contacting the vault…',
        'win' => 'VAULT OPENED', 'bigWin' => 'COSMIC WIN', 'hugeWin' => 'SUPERNOVA WIN'];

    public function toDOM(): \DOMElement
    {
        $rules_class = static::RULES;
        $root = Section::toDOM();
        $root -> setAttribute('data-vlt-game', $rules_class::GAME);
        $root -> setAttribute('data-vlt-presentation', json_encode(static::PRESENTATION, JSON_THROW_ON_ERROR));
        $root -> setAttribute('data-symbols', json_encode($rules_class::SYMBOLS, JSON_THROW_ON_ERROR));
        $root -> setAttribute('data-lines', json_encode(defined($rules_class . '::LINES') ? $rules_class::LINES : [], JSON_THROW_ON_ERROR));
        $root -> setAttribute('data-rows', (string) static::ROWS);
        $this -> chrome($root);
        $machine = $this -> element('section', ['class' => 'VLTCabinet', 'aria-label' => static::PRESENTATION['name'] . ' video lottery terminal']);
        $machine -> appendChild($this -> element('div', ['class' => 'VLTScene', 'aria-hidden' => 'true']));
        $header = $this -> element('header', ['class' => 'VLTMarquee']);
        $header -> appendChild($this -> element('p', ['class' => 'VLTEyebrow'], static::PRESENTATION['eyebrow']));
        $header -> appendChild($this -> element('h1', [], strtoupper(static::PRESENTATION['name'])));
        $header -> appendChild($this -> element('p', ['class' => 'VLTTagline'], static::PRESENTATION['tagline']));
        $machine -> appendChild($header);
        $meters = $this -> element('div', ['class' => 'VLTMeters']);
        $meters -> appendChild($this -> element('span', [], static::METER));
        $meters -> appendChild($this -> element('strong', ['class' => 'VLTPower'], strtoupper(static::PRESENTATION['feature']) . ' · 1×'));
        $machine -> appendChild($meters);
        $machine -> appendChild($this -> boardDOM());
        $readout = $this -> element('div', ['class' => 'VLTReadout']);
        $readout -> appendChild($this -> element('strong', ['class' => 'VLTWinTitle'], static::PRESENTATION['welcome']));
        $readout -> appendChild($this -> element('output', ['class' => 'VLTWinAmount', 'aria-label' => 'Chips returned'], '0'));
        $readout -> appendChild($this -> element('p', ['class' => 'VLTResult', 'role' => 'status'], 'Choose your stake and start the machine.'));
        $machine -> appendChild($readout);
        $form = (new VLTForm()) -> toDOM();
        $label = $this -> element('label', [], 'Total Stake ');
        $select = $this -> element('select', ['name' => 'stake', 'aria-label' => 'Total chips per spin']);
        foreach ($rules_class::STAKES as $stake) {
            $option = $this -> element('option', ['value' => $stake], $stake . ' chips');
            if ($stake === 20) $option -> setAttribute('selected', '');
            $select -> appendChild($option);
        }
        $label -> appendChild($select); $form -> appendChild($label);
        $form -> appendChild($this -> element('button', ['type' => 'submit', 'class' => 'VLTSpin', 'disabled' => ''], static::PRESENTATION['spin']));
        $form -> appendChild($this -> element('button', ['type' => 'button', 'class' => 'VLTSound', 'aria-pressed' => 'false'], 'Enable Sound'));
        $machine -> appendChild($form);
        $machine -> appendChild($this -> element('div', ['class' => 'VLTWins', 'aria-label' => 'Wins']));
        $root -> appendChild($machine);
        $root -> appendChild($this -> rulesDOM());
        $this -> footer($root);
        return $root;
    }

    protected function boardDOM(): \DOMElement
    {
        $rules_class = static::RULES;
        $window = $this -> element('div', ['class' => 'VLTWindow', 'aria-label' => 'Five columns, ' . static::ROWS . ' symbols per column']);
        for ($reel = 0; $reel < 5; $reel++) {
            $column = $this -> element('div', ['class' => 'VLTReel']);
            for ($row = 0; $row < static::ROWS; $row++) $column -> appendChild($this -> element('span', ['class' => 'VLTSymbol'], $rules_class::SYMBOLS[($reel + $row) % 6]['glyph']));
            $window -> appendChild($column);
        }
        return $window;
    }

    protected function rulesDOM(): \DOMElement
    {
        $rules_class = static::RULES;
        $rules = $this -> element('details', ['class' => 'VLTRules']);
        $rules -> appendChild($this -> element('summary', [], 'Paytable, Paylines & Odds'));
        $rules -> appendChild($this -> element('p', [], 'Your total stake is divided equally across ten fixed paylines. Match three or more identical symbols from the leftmost reel on a line. Only the longest match pays on each line; wins on different lines add together. There are no wilds or scatters.'));
        $table = $this -> element('table');
        $table -> appendChild($this -> element('caption', [], 'Returns as multiples of the stake on one line, before the ' . static::PRESENTATION['feature'] . ' multiplier'));
        $head = $this -> element('tr');
        foreach (['Symbol', '3 Reels', '4 Reels', '5 Reels', 'Per Cell'] as $label) $head -> appendChild($this -> element('th', ['scope' => 'col'], $label));
        $table -> appendChild($head);
        foreach ($rules_class::SYMBOLS as $symbol) {
            $row = $this -> element('tr');
            $row -> appendChild($this -> element('th', ['scope' => 'row'], $symbol['glyph'] . ' ' . $symbol['name']));
            foreach ($symbol['pays'] as $payout) $row -> appendChild($this -> element('td', [], $payout . '×'));
            $row -> appendChild($this -> element('td', [], $symbol['weight'] . '/32')); $table -> appendChild($row);
        }
        $rules -> appendChild($table);
        $rules -> appendChild($this -> element('p', [], 'Each reel independently selects one of 32 equally likely stops. Adjacent cells come from its fixed reel strip. Independently, each spin uses a 1× multiplier with 90% probability, 2× with 8%, or 5× with 2%. The multiplier applies to all line returns; it does not guarantee a win.'));
        $rules -> appendChild($this -> element('p', [], 'Theoretical return: ' . number_format($rules_class::returnRate() * 100, 4) . '% of chips wagered over the long run, not a promise for any session. Payouts include any returned stake. Every spin is independent; previous results do not change the odds.'));
        $rules -> appendChild($this -> element('div', ['class' => 'VLTLineGuide', 'aria-label' => 'All ten payline paths']));
        return $rules;
    }
}
