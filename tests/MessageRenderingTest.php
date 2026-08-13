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

        $body = new \DOMXPath(HTMLObject::currentDocument()) -> query('.//*[@class="MessageBody"]', $element) -> item(0);
        $this -> assertSame('Encrypted message', $body -> textContent);
    }

    public function testAPlaintextMessageRendersItsBodyAndCarriesNoEnvelope(): void
    {
        $message = $this -> message();
        $message -> body = 'hello there';

        $element = $this -> elementFor($message);

        $this -> assertFalse($element -> hasAttribute('data-cipher-envelope'));
        $this -> assertFalse(str_contains($element -> getAttribute('class'), 'Locked'));

        $body = new \DOMXPath(HTMLObject::currentDocument()) -> query('.//*[@class="MessageBody"]', $element) -> item(0);
        $this -> assertSame('hello there', $body -> textContent);
    }

    /**
     * The line breaks and the indentation somebody typed are part of what they
     * said. Asserted on the serialized markup as well as the DOM, because a
     * formatter that indents the children of an element would rewrite pasted
     * code on its way out and every assertion against the tree would still pass.
     */
    public function testAMessageKeepsTheLineBreaksAndIndentationItWasWrittenWith(): void
    {
        $written = "def greet(name):\n    print('hi ' + name)\n\ngreet('you')";

        $message = $this -> message();
        $message -> body = $written;

        $element = $this -> elementFor($message);
        $body = new \DOMXPath(HTMLObject::currentDocument()) -> query('.//*[@class="MessageBody"]', $element) -> item(0);

        $this -> assertSame('pre', $body -> tagName, 'the shape is in the markup, not only in a stylesheet');
        $this -> assertSame($written, $body -> textContent);
        $this -> assertTrue(str_contains((string) HTMLObject::currentDocument() -> saveXML($element), $written), 'the written lines survive serialization');
    }

    private function composer(bool $recipient_is_local): \DOMElement
    {
        $state = $recipient_is_local ? 'awaiting-yours' : 'federated';

        return $this -> elementFor(new MessageComposer(9, new MessagePrivacyButton($state, '@someone'), $recipient_is_local));
    }

    public function testTheComposerNamesWhoTheThreadIsWith(): void
    {
        // The composer rather than the list, because a thread nobody has
        // written in yet renders no list at all - and a live message arriving
        // in one still has to be recognised as belonging here.
        $this -> assertSame('9', $this -> composer(true) -> getAttribute('data-other-user-id'));
    }

    public function testNoCallIsOfferedInAThreadWithSomeoneElsewhere(): void
    {
        // There is no way to negotiate a direct browser-to-browser path with
        // someone on another server, and no relay to fall back on. The absent
        // attribute is what turns calling off.
        $element = $this -> composer(false);

        $this -> assertFalse($element -> hasAttribute('data-other-user-id'));
        $this -> assertFalse($element -> hasAttribute('data-ice-servers'));
    }
}
