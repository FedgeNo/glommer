<?php

declare(strict_types=1);

/**
 * A remote account's labelled fields, on the page.
 *
 * Each is a name and the thing it names, which is what a description list is
 * for - a screen reader announces the pair, where two divs would announce two
 * unrelated strings. The values go through the same linkifier a bio does, so a
 * field holding an address is one a reader can follow.
 */
class UserFieldsTest extends TestCase
{
    /** @param array<int, array{name: string, value: string}> $fields */
    private function render(array $fields): \DOMXPath
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        $list = new UserFields();
        $list -> fields = $fields;

        $element = $list -> toDOM();
        HTMLObject::currentDocument() -> appendChild($element);

        return new \DOMXPath(HTMLObject::currentDocument());
    }

    public function testEachFieldIsANameAndTheThingItNames(): void
    {
        $xpath = $this -> render([
            ['name' => 'Pronouns', 'value' => 'they/them'],
            ['name' => 'Location', 'value' => 'Somewhere'],
        ]);

        $this -> assertSame(1, $xpath -> query('//dl[@class="UserFields"]') -> length);
        $this -> assertSame(2, $xpath -> query('//dt') -> length);
        $this -> assertSame(2, $xpath -> query('//dd') -> length);
        $this -> assertSame('Pronouns', $xpath -> query('//dt') -> item(0) -> textContent);
        $this -> assertSame('they/them', $xpath -> query('//dd') -> item(0) -> textContent);
    }

    /** The pairs stay in step - a name is followed by its own value. */
    public function testANameIsFollowedByItsOwnValue(): void
    {
        $xpath = $this -> render([
            ['name' => 'Pronouns', 'value' => 'they/them'],
            ['name' => 'Location', 'value' => 'Somewhere'],
        ]);

        $this -> assertSame('Location', $xpath -> query('//dt') -> item(1) -> textContent);
        $this -> assertSame('Somewhere', $xpath -> query('//dd') -> item(1) -> textContent);
    }

    /** A field holding an address is one a reader can follow. */
    public function testAnAddressBecomesALink(): void
    {
        $xpath = $this -> render([['name' => 'Website', 'value' => 'https://example.test/me']]);

        $anchor = $xpath -> query('//dd//a') -> item(0);

        $this -> assertNotNull($anchor);
        $this -> assertSame('https://example.test/me', $anchor -> getAttribute('href'));
    }

    /**
     * The value is text, whatever it looks like. A field arriving with markup
     * in it has already been reduced to words by RemoteActorFields - this is
     * the second half of that: nothing here builds an element out of them.
     */
    public function testAValueThatLooksLikeMarkupIsStillText(): void
    {
        $xpath = $this -> render([['name' => 'About', 'value' => '<b>not bold</b>']]);

        $this -> assertSame(0, $xpath -> query('//dd//b') -> length);
        $this -> assertSame('<b>not bold</b>', $xpath -> query('//dd') -> item(0) -> textContent);
    }
}
