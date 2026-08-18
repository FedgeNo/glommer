<?php

declare(strict_types=1);

/**
 * The signed-in profile card's action column: View Friends, the avatar
 * picker, Update Avatar. A grid (components.css) so every control takes the
 * width of the widest one - a column of same-size controls, not a ragged
 * stack of labels.
 */
class CurrentUserActions extends Div
{
    public ?string $class = 'CurrentUserActions';
}
