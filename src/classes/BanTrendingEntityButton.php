<?php

declare(strict_types=1);

class BanTrendingEntityButton extends Button
{
    public function __construct(string $entity_type, string $entity_value)
    {
        parent::__construct();

        $this -> class = 'Button BanTrendingEntityButton';
        $this -> attributes['data-entity-type'] = $entity_type;
        $this -> attributes['data-entity-value'] = $entity_value;
        $this -> addContent('Ban');
    }
}
