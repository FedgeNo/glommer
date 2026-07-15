<?php

declare(strict_types=1);

class HTMLObjectTest extends TestCase
{
    public function testRendersTagNameAndTextContent(): void
    {
        $paragraph = new Paragraph('hello world');

        $this -> assertSame('<p>hello world</p>', $paragraph -> render());
    }

    public function testRendersClassAttributeWhenSet(): void
    {
        $div = new Div();
        $div -> class = 'Card';
        $div -> addContent('content');

        $this -> assertSame('<div class="Card">content</div>', $div -> render());
    }

    public function testOmitsClassAttributeWhenNull(): void
    {
        $div = new Div();
        $div -> addContent('content');

        $this -> assertSame('<div>content</div>', $div -> render());
    }

    public function testNestsChildHTMLObjects(): void
    {
        // render()'s DOMDocument has formatOutput on, so a nested element
        // pretty-prints with indentation - not compact markup.
        $div = new Div();
        $div -> addContent(new Paragraph('inner'));

        $this -> assertSame("<div>\n  <p>inner</p>\n</div>", $div -> render());
    }

    public function testTextContentIsEscaped(): void
    {
        // createTextNode(), not raw markup - the same reason the whole app
        // never uses innerHTML on the JS side. A literal "<" in user text
        // must never be interpreted as a tag.
        $paragraph = new Paragraph('<script>alert(1)</script>');

        $this -> assertSame('<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>', $paragraph -> render());
    }

    public function testCustomAttributesAreRendered(): void
    {
        $div = new Div();
        $div -> attributes['data-user-id'] = '42';
        $div -> addContent('x');

        $this -> assertSame('<div data-user-id="42">x</div>', $div -> render());
    }

    public function testSubclassDeclaringItsOwnClassGetsNoAutoChaining(): void
    {
        // Notice extends Paragraph and redeclares $class itself - so it IS
        // its own declaring class, and gets exactly what it set, no
        // PHP-class-name chaining on top.
        $notice = new Notice('careful now');

        $this -> assertSame('Muted Notice', $notice -> class);
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
