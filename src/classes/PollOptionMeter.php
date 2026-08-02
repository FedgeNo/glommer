<?php

declare(strict_types=1);

/**
 * The bar showing one option's share of the vote.
 *
 * A <meter> rather than a styled div: a share of a total is exactly what the
 * element means, so a reader who cannot see the bar is still told the figure by
 * their browser instead of being handed decoration.
 */
class PollOptionMeter extends Meter
{
    public ?string $class = 'PollOptionMeter';

    public function __construct(int $share)
    {
        parent::__construct();

        $this -> attributes['value'] = (string) $share;
        $this -> attributes['min'] = '0';
        $this -> attributes['max'] = '100';
    }
}
