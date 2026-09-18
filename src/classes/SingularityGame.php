<?php

declare(strict_types=1);

class SingularityGame extends VLTGame
{
    protected const RULES = Singularity::class;
    protected const ROWS = Singularity::SIZE;
    protected const METER = '5+ CONNECTED · CHAIN REACTIONS';
    protected const PRESENTATION = ['name' => 'Singularity', 'eyebrow' => 'REALITY CONTAINMENT / OFFLINE',
        'tagline' => 'Connect. Detonate. Cascade. Tear reality apart.', 'feature' => 'Overdrive',
        'welcome' => 'REACTOR STANDING BY', 'spin' => 'IGNITE THE REACTOR', 'pending' => 'REACTOR IGNITING…',
        'charging' => 'CONTAINMENT BREACH', 'contact' => 'Connecting to the reactor…',
        'win' => 'ENERGY RELEASE', 'bigWin' => 'REACTOR MELTDOWN', 'hugeWin' => 'REALITY RUPTURE'];

    protected function rulesDOM(): \DOMElement
    {
        $rules = $this -> element('details', ['class' => 'VLTRules']);
        $rules -> appendChild($this -> element('summary', [], 'Clusters, Cascades & Paytable'));
        $rules -> appendChild($this -> element('p', [], 'Connect five or more identical symbols horizontally or vertically anywhere on the 5 × 5 board. Diagonals do not connect. Every separate qualifying group pays, including multiple groups of the same symbol. Winning groups explode together, survivors fall down, and new symbols fill the gaps.'));
        $rules -> appendChild($this -> element('p', [], 'The first winning cascade pays 1×, the next 2×, then 3× and so on, up to 12 paid cascades. The cycle ends when there are no winning clusters or after the twelfth paid cascade, with no further refill. Each new play resets the multiplier to 1×.'));
        $table = $this -> element('table');
        $table -> appendChild($this -> element('caption', [], 'Chips returned per 10 chips staked, before the cascade multiplier. Only the highest qualifying size tier pays per group.'));
        $head = $this -> element('tr');
        foreach (['Symbol', '5–6', '7–8', '9–11', '12–25'] as $label) $head -> appendChild($this -> element('th', ['scope' => 'col'], $label));
        $table -> appendChild($head);
        foreach (Singularity::SYMBOLS as $symbol) {
            $row = $this -> element('tr');
            $row -> appendChild($this -> element('th', ['scope' => 'row'], $symbol['glyph'] . ' ' . $symbol['name']));
            foreach ($symbol['pays'] as $pay) $row -> appendChild($this -> element('td', [], (string) $pay));
            $table -> appendChild($row);
        }
        $rules -> appendChild($table);
        $rules -> appendChild($this -> element('p', [], 'Estimated long-run return: about 95.6% of chips wagered (1,000,000 simulated cycles; approximate 95% sampling interval 95.1–96.2%). This is an estimate, not an exact theoretical return or a promise for your session.'));
        $rules -> appendChild($this -> element('p', [], 'Every new cell independently draws one of the six symbols with equal probability (1/6 each). All cascade returns add together and include any returned stake. The server settles the complete cycle once; recovering an interrupted play retrieves the same cycle.'));
        return $rules;
    }
}
