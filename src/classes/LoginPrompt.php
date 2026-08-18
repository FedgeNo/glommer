<?php

declare(strict_types=1);

/**
 * The line offering a way in, where the thing somebody tried to do needs an
 * account.
 *
 * Holds the shape of the sentence and nothing anybody reads: a slot, the link,
 * a slot. Which slot carries the words is the translation's business, so a
 * language that ends on the control fills the first and empties the last, and
 * the link's own label travels with them - see src/locales/de.php, which does
 * exactly that.
 *
 * The words are read in toDOM() rather than in the constructor, like every
 * other query this render system makes: an object built early and sent late
 * would otherwise be fixed in whatever language was current when somebody
 * happened to construct it.
 */
class LoginPrompt extends Paragraph
{
    public ?string $class = 'LoginPrompt';

    public function __construct(private readonly string $action)
    {
        parent::__construct();
    }

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);
        // An action nobody has written a sentence for still says something,
        // rather than rendering an empty line with a bare link in it.
        $sentence = $words[$this -> action] ?? reset($words);

        $this -> contents[] = (string) ($sentence['before'] ?? '');
        $this -> addContent(new Anchor(ServerURL::absolute('/login'), (string) ($sentence['link'] ?? '')));
        $this -> contents[] = (string) ($sentence['after'] ?? '');

        return parent::toDOM();
    }
}
