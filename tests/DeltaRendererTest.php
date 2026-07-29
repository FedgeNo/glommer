<?php

declare(strict_types=1);

// Initialize the global DOM document before any tests use it.
(function () {
    $prop = new ReflectionProperty(DOMObject::class, 'document');
    $prop -> setAccessible(true);
    $prop -> setValue(null, new DOMDocument('1.0', 'UTF-8'));
})();

class DeltaRendererTest extends TestCase
{
    // ---------- basic rendering ----------

    public function testEmptyOpsReturnsEmptyPostBody()
    {
        $el = (new DeltaRenderer([])) -> toDOM();
        $this -> assertSame('div', $el -> tagName);
        $this -> assertSame('PostBody', $el -> getAttribute('class'));
        $elements = [];
        foreach ($el -> childNodes as $node) {
            if ($node -> nodeType === XML_ELEMENT_NODE) {
                $elements[] = $node;
            }
        }
        $this -> assertCount(0, $elements);
    }

    public function testSinglePlainParagraph()
    {
        $el = (new DeltaRenderer([['insert' => "Hello world\n"]])) -> toDOM();
        $p = $el -> getElementsByTagName('p') -> item(0);
        $this -> assertNotNull($p);
        $this -> assertSame('Hello world', $p -> textContent);
    }

    public function testTwoParagraphs()
    {
        $ops = [
            ['insert' => "First line\n"],
            ['insert' => "Second line\n"],
        ];
        $el = (new DeltaRenderer($ops)) -> toDOM();
        $ps = $el -> getElementsByTagName('p');
        $this -> assertSame(2, $ps -> length);
        $this -> assertSame('First line', $ps -> item(0) -> textContent);
        $this -> assertSame('Second line', $ps -> item(1) -> textContent);
    }

    public function testEmptyLineProducesBreak()
    {
        $el = (new DeltaRenderer([['insert' => "\n"]])) -> toDOM();
        $p = $el -> getElementsByTagName('p') -> item(0);
        $this -> assertNotNull($p);
        $this -> assertSame(1, $p -> getElementsByTagName('br') -> length);
    }

    // ---------- block formats ----------

    public function testHeaderLevel1()
    {
        $ops = [
            ['insert' => 'Title'],
            ['insert' => "\n", 'attributes' => ['header' => 1]],
        ];
        $el = (new DeltaRenderer($ops)) -> toDOM();
        $h1 = $el -> getElementsByTagName('h1') -> item(0);
        $this -> assertNotNull($h1);
        $this -> assertSame('Title', $h1 -> textContent);
    }

    public function testHeaderLevel2()
    {
        $ops = [
            ['insert' => 'Section'],
            ['insert' => "\n", 'attributes' => ['header' => 2]],
        ];
        $el = (new DeltaRenderer($ops)) -> toDOM();
        $this -> assertNotNull($el -> getElementsByTagName('h2') -> item(0));
    }

    public function testBlockquote()
    {
        $ops = [
            ['insert' => "Quoted\n"],
            ['attributes' => ['blockquote' => true], 'insert' => "\n"],
        ];
        $el = (new DeltaRenderer($ops)) -> toDOM();
        $this -> assertNotNull($el -> getElementsByTagName('blockquote') -> item(0));
    }

    public function testCodeBlock()
    {
        $ops = [
            ['insert' => "code();\n"],
            ['attributes' => ['code-block' => true], 'insert' => "\n"],
        ];
        $el = (new DeltaRenderer($ops)) -> toDOM();
        $this -> assertNotNull($el -> getElementsByTagName('pre') -> item(0));
    }

    public function testOrderedList()
    {
        $ops = [
            ['insert' => "Item 1\n", 'attributes' => ['list' => 'ordered']],
            ['insert' => "Item 2\n", 'attributes' => ['list' => 'ordered']],
        ];
        $el = (new DeltaRenderer($ops)) -> toDOM();
        $ol = $el -> getElementsByTagName('ol') -> item(0);
        $this -> assertNotNull($ol);
        $this -> assertSame(2, $ol -> getElementsByTagName('li') -> length);
    }

    public function testBulletList()
    {
        $ops = [
            ['insert' => "Item A\n", 'attributes' => ['list' => 'bullet']],
            ['insert' => "Item B\n", 'attributes' => ['list' => 'bullet']],
        ];
        $el = (new DeltaRenderer($ops)) -> toDOM();
        $this -> assertNotNull($el -> getElementsByTagName('ul') -> item(0));
    }

    // ---------- inline formatting ----------

    public function testBold()
    {
        $ops = [['insert' => "bold", 'attributes' => ['bold' => true]], ['insert' => "\n"]];
        $el = (new DeltaRenderer($ops)) -> toDOM();
        $strong = $el -> getElementsByTagName('strong') -> item(0);
        $this -> assertNotNull($strong);
        $this -> assertSame('bold', $strong -> textContent);
    }

    public function testItalic()
    {
        $ops = [['insert' => "italic", 'attributes' => ['italic' => true]], ['insert' => "\n"]];
        $el = (new DeltaRenderer($ops)) -> toDOM();
        $this -> assertNotNull($el -> getElementsByTagName('em') -> item(0));
    }

    public function testUnderline()
    {
        $ops = [['insert' => "under", 'attributes' => ['underline' => true]], ['insert' => "\n"]];
        $el = (new DeltaRenderer($ops)) -> toDOM();
        $this -> assertNotNull($el -> getElementsByTagName('u') -> item(0));
    }

    public function testStrikethrough()
    {
        $ops = [['insert' => "strike", 'attributes' => ['strike' => true]], ['insert' => "\n"]];
        $el = (new DeltaRenderer($ops)) -> toDOM();
        $this -> assertNotNull($el -> getElementsByTagName('s') -> item(0));
    }

    public function testInlineCode()
    {
        $ops = [['insert' => "var x = 1;", 'attributes' => ['code' => true]], ['insert' => "\n"]];
        $el = (new DeltaRenderer($ops)) -> toDOM();
        $code = $el -> getElementsByTagName('code') -> item(0);
        $this -> assertNotNull($code);
        $this -> assertSame('var x = 1;', $code -> textContent);
    }

    public function testMultipleFormats()
    {
        $ops = [['insert' => "bold italic", 'attributes' => ['bold' => true, 'italic' => true]], ['insert' => "\n"]];
        $el = (new DeltaRenderer($ops)) -> toDOM();
        $this -> assertSame(1, $el -> getElementsByTagName('strong') -> length);
        $this -> assertSame(1, $el -> getElementsByTagName('em') -> length);
        $this -> assertSame('bold italic', $el -> textContent);
    }

    // ---------- links ----------

    public function testSafeLinkOpensInNewTab()
    {
        $ops = [['insert' => "click here", 'attributes' => ['link' => 'https://example.com']], ['insert' => "\n"]];
        $el = (new DeltaRenderer($ops)) -> toDOM();
        $anchor = $el -> getElementsByTagName('a') -> item(0);
        $this -> assertNotNull($anchor);
        $this -> assertSame('https://example.com', $anchor -> getAttribute('href'));
        $this -> assertSame('_blank', $anchor -> getAttribute('target'));
    }

    public function testInternalLinkSameHostDoesNotOpenNewTab()
    {
        $ops = [['insert' => "profile", 'attributes' => ['link' => '/users/joe']], ['insert' => "\n"]];
        $el = (new DeltaRenderer($ops)) -> toDOM();
        $anchor = $el -> getElementsByTagName('a') -> item(0);
        $this -> assertNotNull($anchor);
        $this -> assertSame('', $anchor -> getAttribute('target'));
    }

    public function testJavascriptLinkIsStrippedEntirely()
    {
        $ops = [['insert' => "click", 'attributes' => ['link' => 'javascript:alert(1)']], ['insert' => "\n"]];
        $el = (new DeltaRenderer($ops)) -> toDOM();
        $this -> assertSame(0, $el -> getElementsByTagName('a') -> length);
    }

    public function testMailtoLinkIsPreserved()
    {
        $ops = [['insert' => "email", 'attributes' => ['link' => 'mailto:test@example.com']], ['insert' => "\n"]];
        $el = (new DeltaRenderer($ops)) -> toDOM();
        $anchor = $el -> getElementsByTagName('a') -> item(0);
        $this -> assertNotNull($anchor);
        $this -> assertSame('mailto:test@example.com', $anchor -> getAttribute('href'));
    }

    // ---------- honest‑links (pass 1) ----------

    public function testDeceptiveLinkIsStripped()
    {
        $ops = [['insert' => 'https://evil.com', 'attributes' => ['link' => 'https://good.com']], ['insert' => "\n"]];
        $el = (new DeltaRenderer($ops)) -> toDOM();
        $anchors = $el -> getElementsByTagName('a');
        $this -> assertSame(1, $anchors -> length);
        $this -> assertSame('https://evil.com', $anchors -> item(0) -> getAttribute('href'));
    }

    public function testHonestLinkIsPreserved()
    {
        $ops = [['insert' => 'https://example.com', 'attributes' => ['link' => 'https://example.com']], ['insert' => "\n"]];
        $el = (new DeltaRenderer($ops)) -> toDOM();
        $this -> assertSame(1, $el -> getElementsByTagName('a') -> length);
    }

    // ---------- linkification (pass 2) ----------

    public function testBareUrlBecomesLink()
    {
        $el = (new DeltaRenderer([['insert' => "Visit https://example.com today\n"]])) -> toDOM();
        $anchors = $el -> getElementsByTagName('a');
        $this -> assertSame(1, $anchors -> length);
        $this -> assertSame('https://example.com', $anchors -> item(0) -> getAttribute('href'));
    }

    public function testHashtagBecomesTagLink()
    {
        $el = (new DeltaRenderer([['insert' => "Check #glommer out\n"]])) -> toDOM();
        $anchors = $el -> getElementsByTagName('a');
        $this -> assertSame(1, $anchors -> length);
        $href = $anchors -> item(0) -> getAttribute('href');
        $this -> assertTrue(str_contains($href, '/tags/glommer'));
    }

    public function testMentionBecomesProfileLink()
    {
        $el = (new DeltaRenderer([['insert' => "Hello @admin\n"]])) -> toDOM();
        $anchors = $el -> getElementsByTagName('a');
        $this -> assertSame(1, $anchors -> length);
        $href = $anchors -> item(0) -> getAttribute('href');
        $this -> assertTrue(str_contains($href, '/users/admin/'));
    }

    // ---------- safe‑link utility ----------

    public function testIsSafeLinkAllowsHttp()
    {
        $this -> assertTrue(DeltaRenderer::isSafeLink('http://example.com'));
    }

    public function testIsSafeLinkAllowsHttps()
    {
        $this -> assertTrue(DeltaRenderer::isSafeLink('https://example.com'));
    }

    public function testIsSafeLinkAllowsMailto()
    {
        $this -> assertTrue(DeltaRenderer::isSafeLink('mailto:test@example.com'));
    }

    public function testIsSafeLinkRejectsJavascript()
    {
        $this -> assertFalse(DeltaRenderer::isSafeLink('javascript:alert(1)'));
    }

    public function testIsSafeLinkRejectsJavascriptWithWhitespace()
    {
        $this -> assertFalse(DeltaRenderer::isSafeLink("java\tscript:alert(1)"));
    }

    public function testIsSafeLinkAllowsRelativePath()
    {
        $this -> assertTrue(DeltaRenderer::isSafeLink('/users/admin'));
    }

    // ---------- formula embed ----------

    public function testFormulaEmbedRendersSpan()
    {
        $ops = [['insert' => ['formula' => 'x^2']], ['insert' => "\n"]];
        $el = (new DeltaRenderer($ops)) -> toDOM();
        $spans = $el -> getElementsByTagName('span');
        $this -> assertSame(1, $spans -> length);
        $span = $spans -> item(0);
        $this -> assertSame('PostFormula', $span -> getAttribute('class'));
        $this -> assertSame('x^2', $span -> getAttribute('data-formula'));
    }
}
