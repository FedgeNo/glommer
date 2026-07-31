<?php

declare(strict_types=1);

// A Form that contributes its own name to the shared identity chain: a subclass
// that doesn't override $class itself renders as "Form <ClassName>"
// automatically (and "Form <Middle> <ClassName>" through an abstract middle
// class), so the look every form shares hangs off one selector instead of each
// form repeating it. A form with no identity of its own, or one that
// deliberately skips the shared styling, extends Form directly instead.
class FormForm extends Form
{
    public ?string $class = 'Form';
}
