<?php

declare(strict_types=1);

class DeltaTest extends TestCase
{
    // ---------- decode ----------

    public function testDecodeReturnsEmptyArrayForNullInput()
    {
        $this -> assertSame([], Delta::decode(null));
    }

    public function testDecodeReturnsEmptyArrayForEmptyString()
    {
        $this -> assertSame([], Delta::decode(''));
    }

    public function testDecodeReturnsEmptyArrayForInvalidJson()
    {
        $this -> assertSame([], Delta::decode('{invalid'));
    }

    public function testDecodeExtractsOpsFromValidJson()
    {
        $json = '{"ops":[{"insert":"Hello\\n"}]}';
        $ops  = Delta::decode($json);
        $this -> assertCount(1, $ops);
        $this -> assertSame("Hello\n", $ops[0]['insert']);
    }

    public function testDecodeReturnsEmptyArrayWhenOpsKeyIsMissing()
    {
        $json = '{"something":"else"}';
        $this -> assertSame([], Delta::decode($json));
    }

    // ---------- sanitize ----------

    public function testSanitizeRemovesNonStringInsertsThatAreNotEmbeds()
    {
        $ops   = [['insert' => 123], ['insert' => "\n"]];
        $clean = Delta::sanitize($ops);
        $this -> assertCount(1, $clean);
        $this -> assertSame("\n", $clean[0]['insert']);
    }

    public function testSanitizePreservesStringInserts()
    {
        $ops   = [['insert' => "Hello\n"]];
        $clean = Delta::sanitize($ops);
        $this -> assertSame("Hello\n", $clean[0]['insert']);
    }

    public function testSanitizePreservesFormulaEmbeds()
    {
        $ops   = [['insert' => ['formula' => 'x^2']], ['insert' => "\n"]];
        $clean = Delta::sanitize($ops);
        $this -> assertCount(2, $clean);
        $this -> assertSame('x^2', $clean[0]['insert']['formula']);
    }

    public function testSanitizeStripsDisallowedAttributes()
    {
        $ops   = [['insert' => "text", 'attributes' => ['bold' => true, 'evil' => 'yes']], ['insert' => "\n"]];
        $clean = Delta::sanitize($ops);
        $this -> assertTrue(isset($clean[0]['attributes']), 'attributes key should exist');
        $this -> assertTrue($clean[0]['attributes']['bold'], 'bold should be preserved');
        $this -> assertFalse(isset($clean[0]['attributes']['evil']), 'evil should be stripped');
    }

    public function testSanitizeStripsLinkAttributeWhenValueIsNotAString()
    {
        $ops   = [['insert' => "link", 'attributes' => ['link' => 123]], ['insert' => "\n"]];
        $clean = Delta::sanitize($ops);
        $this -> assertFalse(isset($clean[0]['attributes']['link']), 'non-string link should be stripped');
    }

    public function testSanitizePreservesLinkAttributeWhenValueIsAString()
    {
        $ops   = [['insert' => "click", 'attributes' => ['link' => 'https://example.com']], ['insert' => "\n"]];
        $clean = Delta::sanitize($ops);
        $this -> assertTrue(isset($clean[0]['attributes']['link']), 'string link should be preserved');
        $this -> assertSame('https://example.com', $clean[0]['attributes']['link']);
    }

    public function testSanitizeRemovesAttributesWhenEmptyAfterCleaning()
    {
        $ops   = [['insert' => "text", 'attributes' => ['evil' => 'x']], ['insert' => "\n"]];
        $clean = Delta::sanitize($ops);
        $this -> assertFalse(isset($clean[0]['attributes']), 'attributes key should be removed when all were stripped');
    }

    // ---------- plainText ----------

    public function testPlainTextExtractsStringInserts()
    {
        $ops  = [['insert' => "Hello world\n"]];
        $text = Delta::plainText($ops);
        $this -> assertSame('Hello world', $text);
    }

    public function testPlainTextExtractsFormulaText()
    {
        $ops  = [['insert' => ['formula' => 'x^2']], ['insert' => "\n"]];
        $text = Delta::plainText($ops);
        // plainText includes formula source text, unlike my original assumption
        $this -> assertSame('x^2', $text);
    }

    public function testPlainTextCollapsesNewlinesToSpaces()
    {
        $ops  = [['insert' => "Line one\nLine two\n"]];
        $text = Delta::plainText($ops);
        $this -> assertSame('Line one Line two', $text);
    }

    public function testPlainTextTrimsWhitespace()
    {
        $ops  = [['insert' => "  padded  \n"]];
        $text = Delta::plainText($ops);
        $this -> assertSame('padded', $text);
    }
}
