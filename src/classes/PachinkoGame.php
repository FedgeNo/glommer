<?php

declare(strict_types=1);

class PachinkoGame extends VLTGame
{
    protected const RULES = Pachinko::class;
    protected const METER = '12 BOUNCES · 13 POCKETS';
    protected const PRESENTATION = ['name' => 'Neon Pachinko', 'eyebrow' => 'CHROME / LIGHT / LUCK',
        'tagline' => 'One ball. A thousand sparks.', 'feature' => 'Pocket',
        'welcome' => 'READY TO DROP', 'spin' => 'LAUNCH BALL', 'pending' => 'BALL IN PLAY…',
        'charging' => 'CHARGING THE LAUNCHER', 'contact' => 'Preparing your ball…',
        'win' => 'POCKET WIN', 'bigWin' => 'NEON SURGE', 'hugeWin' => 'JACKPOT · 50×'];

    protected function boardDOM(): \DOMElement
    {
        $board = $this -> element('div', ['class' => 'PachinkoBoard', 'data-returns' => json_encode(Pachinko::RETURNS, JSON_THROW_ON_ERROR), 'aria-label' => 'Neon pachinko pinboard']);
        $board -> appendChild($this -> element('span', ['class' => 'PachinkoLoading'], 'Lighting up the pinboard…'));
        $pockets = $this -> element('div', ['class' => 'PachinkoPockets', 'aria-label' => 'Pocket returns from left to right']);
        foreach (Pachinko::RETURNS as $index => $units) $pockets -> appendChild($this -> element('span', ['class' => 'PachinkoPocket', 'data-pocket' => $index], ($units / 10) . '×'));
        $board -> appendChild($pockets);
        return $board;
    }

    protected function rulesDOM(): \DOMElement
    {
        $rules = $this -> element('details', ['class' => 'VLTRules']);
        $rules -> appendChild($this -> element('summary', [], 'Pockets, Payouts & Odds'));
        $rules -> appendChild($this -> element('p', [], 'Launch one ball for your selected stake. At each of twelve pegs it takes an independent, equally likely left or right bounce. The number of right bounces determines the pocket. Returns include any returned stake. The launch animation does not affect the outcome; there is no aiming or timing advantage.'));
        $table = $this -> element('table');
        $table -> appendChild($this -> element('caption', [], 'Pocket probabilities out of 4,096 equally likely paths'));
        $head = $this -> element('tr');
        foreach (['Pocket (Left to Right)', 'Return', 'Probability'] as $label) $head -> appendChild($this -> element('th', ['scope' => 'col'], $label));
        $table -> appendChild($head);
        foreach (Pachinko::RETURNS as $pocket => $units) {
            $row = $this -> element('tr');
            $row -> appendChild($this -> element('th', ['scope' => 'row'], (string) ($pocket + 1)));
            $row -> appendChild($this -> element('td', [], ($units / 10) . '× stake'));
            $row -> appendChild($this -> element('td', [], Pachinko::pocketWays($pocket) . ' / 4,096'));
            $table -> appendChild($row);
        }
        $rules -> appendChild($table);
        $rules -> appendChild($this -> element('p', [], 'Theoretical return: ' . number_format(Pachinko::returnRate() * 100, 4) . '% over the long run, not a promise for any session. The two 50× jackpot pockets together occur on 1 in 2,048 balls. Each ball is independent. Interrupted plays recover the same path and payout without another charge.'));
        return $rules;
    }
}
