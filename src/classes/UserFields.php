<?php

declare(strict_types=1);

/**
 * The labelled fields a remote account publishes about itself, under its bio -
 * "Website", "Pronouns", "Location" and whatever else it chose to say.
 *
 * A description list, because that is what this is: each row is a name and the
 * thing it names, which is what dt and dd are for and what a screen reader
 * announces as a pair.
 */
class UserFields extends DescriptionList
{
    public ?string $class = 'UserFields';

    /** @var array<int, array{name: string, value: string}> */
    public array $fields = [];

    public function toDOM(): \DOMElement
    {
        foreach ($this -> fields as $field) {
            $name = new DescriptionTerm();
            $name -> contents[] = $field['name'];

            $value = new UserFieldValue();
            $value -> value = $field['value'];

            $this -> addContent($name);
            $this -> addContent($value);
        }

        return parent::toDOM();
    }
}
