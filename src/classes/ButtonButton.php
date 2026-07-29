<?php

declare(strict_types=1);

// A Button that contributes its own name to the shared identity chain: a
// subclass that doesn't override $class itself renders as "Button
// <ClassName>" automatically via deriveClassName(), instead of every
// constructor repeating both the class name and the 'Button' mixin. A button
// with no identity of its own, a mismatched identity, or that deliberately
// skips the shared styling extends Button directly instead.
class ButtonButton extends Button
{
    public ?string $class = 'Button';
}
