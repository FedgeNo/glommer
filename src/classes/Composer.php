<?php

declare(strict_types=1);

abstract class Composer extends Form
{
    public ?string $class = 'Composer';
    public array $mixins = ['Card', 'd-flex', 'flex-column'];
}
