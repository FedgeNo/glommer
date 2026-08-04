<?php

declare(strict_types=1);

class MessageRenderingTest extends TestCase
{
    /**
     * toDOM() builds into the shared document the app stands up as it renders
     * a page; a unit test puts a bare one in its place, then attaches the
     * element so a document-wide XPath can reach it.
     */
    private function elementFor(HTMLObject $object): \DOMElement
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        $element = $object -> toDOM();
        HTMLObject::currentDocument() -> appendChild($element);

        return $element;
    }

    private function message(): Message
    {
        $message = new Message();
        $message -> messageId = 7;
        $message -> senderId = 2;
        $message -> recipientId = 3;
        $message -> createdAt = '2026-08-01 12:00:00';

        return $message;
    }

    public function testAnEncryptedMessageRendersLockedWithItsEnvelopeAndNoPlaintext(): void
    {
        $message = $this -> message();
        $message -> bodyCiphertext = '{"v":1,"iv":"abc"}';

        $element = $this -> elementFor($message);
        $classes = explode(' ', $element -> getAttribute('class'));

        $this -> assertTrue(in_array('Encrypted', $classes, true));
        $this -> assertTrue(in_array('Locked', $classes, true));
        $this -> assertSame('{"v":1,"iv":"abc"}', $element -> getAttribute('data-cipher-envelope'));
        $this -> assertSame('7', $element -> getAttribute('data-message-id'));

        $body = new \DOMXPath(HTMLObject::currentDocument()) -> query('.//p', $element) -> item(0);
        $this -> assertSame('Encrypted message', $body -> textContent);
    }

    public function testAPlaintextMessageRendersItsBodyAndCarriesNoEnvelope(): void
    {
        $message = $this -> message();
        $message -> body = 'hello there';

        $element = $this -> elementFor($message);

        $this -> assertFalse($element -> hasAttribute('data-cipher-envelope'));
        $this -> assertFalse(str_contains($element -> getAttribute('class'), 'Locked'));

        $body = new \DOMXPath(HTMLObject::currentDocument()) -> query('.//p', $element) -> item(0);
        $this -> assertSame('hello there', $body -> textContent);
    }
}
