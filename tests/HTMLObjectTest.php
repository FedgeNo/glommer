<?php

declare(strict_types=1);

class HTMLObjectTest extends TestCase
{
    public function testSubclassDeclaringItsOwnClassGetsNoAutoChaining(): void
    {
        // Notice extends Paragraph and redeclares $class itself - so it IS
        // its own declaring class, and gets exactly what it set, no
        // PHP-class-name chaining on top.
        $notice = new Notice('careful now');

        $this -> assertSame('muted Notice', $notice -> class);
    }

    public function testGenericWrapperGetsNoClassAttribute(): void
    {
        // Div/Button/Anchor etc. never declare their own $class - per
        // HTMLObject's constructor, a class that doesn't redeclare $class
        // itself (declaring_class === HTMLObject) is treated as a generic
        // primitive and gets no automatic class name.
        $div = new Div();

        $this -> assertNull($div -> class);
    }
}
