<?php

declare(strict_types=1);

/**
 * Replacing the messaging keys decides who can read every message sent to an
 * account from then on, so api/message-keys.php demands the account password.
 * These check the forms actually offer somewhere to put it - a field named
 * anything else reaches the endpoint as an empty password and every save
 * fails.
 */
class MessageKeyFormTest extends TestCase
{
    /** The secret-carrying fields, in the order they are asked for. */
    private function fieldNames(HTMLObject $form): array
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        $element = $form -> toDOM();
        HTMLObject::currentDocument() -> appendChild($element);

        $names = [];

        foreach (new \DOMXPath(HTMLObject::currentDocument()) -> query('.//input[@type="password"]', $element) as $input) {
            $names[] = $input -> getAttribute('name');
        }

        return $names;
    }

    public function testSetupAsksForThePassphraseTwiceAndTheAccountPassword(): void
    {
        $names = $this -> fieldNames(new MessageKeySetupForm(false));

        $this -> assertSame(['passphrase', 'passphraseConfirm', 'setupAccountPassword'], $names);
    }

    public function testResettingKeysAsksForTheSameThings(): void
    {
        $names = $this -> fieldNames(new MessageKeySetupForm(true));

        $this -> assertSame(['passphrase', 'passphraseConfirm', 'setupAccountPassword'], $names);
    }

    public function testChangingThePassphraseAsksForTheAccountPasswordToo(): void
    {
        $names = $this -> fieldNames(new MessageKeyPassphraseForm());

        $this -> assertSame(['currentPassphrase', 'newPassphrase', 'newPassphraseConfirm', 'rewrapAccountPassword'], $names);
    }

    /**
     * A passphrase typed into a plain text field is readable over someone's
     * shoulder and offered to the browser's autofill as ordinary text, so
     * every one of these has to be a password input - which is also what makes
     * the fieldNames() checks above meaningful.
     */
    public function testNoSecretFieldRendersAsReadableText(): void
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        $element = (new MessageKeyPassphraseForm()) -> toDOM();
        HTMLObject::currentDocument() -> appendChild($element);

        foreach (new \DOMXPath(HTMLObject::currentDocument()) -> query('.//input[@name]', $element) as $input) {
            if ($input -> getAttribute('name') === 'CSRFToken') {
                continue;
            }

            $this -> assertSame('password', $input -> getAttribute('type'), $input -> getAttribute('name') . ' must be a password field');
        }
    }

    /**
     * FormForm posts by default so a submit without JavaScript can never
     * degrade to a GET with the passphrase in the query string - and a POST
     * form is what Form attaches the CSRF token to.
     */
    public function testTheFormPostsAndCarriesACSRFToken(): void
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        $element = (new MessageKeySetupForm(false)) -> toDOM();
        HTMLObject::currentDocument() -> appendChild($element);

        $this -> assertSame('POST', $element -> getAttribute('method'));
        $this -> assertSame(1, new \DOMXPath(HTMLObject::currentDocument()) -> query('.//input[@name="CSRFToken"]', $element) -> length);
    }
}
