<?php

declare(strict_types=1);

/**
 * Which form the subscribing Follow should name.
 *
 * This is protocol minutiae in front of an admin, which is not usually where
 * it belongs - but implementations genuinely disagree here, and there is no
 * way to tell from the address which one a relay wants. A relay that refuses
 * the first form will take the other, and without a way to say so the
 * subscription simply never completes and nothing explains why.
 */
class RelayFollowObjectField extends Div
{
    public ?string $class = 'RelayFollowObjectField';

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);
        $options = is_array($words['options'] ?? null) ? $words['options'] : [];

        $label = new Label();
        $label -> for = 'followObject';
        $label -> contents[] = (string) ($words['label'] ?? '');
        $this -> addContent($label);

        $select = new Select();
        $select -> name = 'followObject';
        $select -> id = 'followObject';

        $styles = [
            Relay::FOLLOW_PUBLIC => (string) ($options[Relay::FOLLOW_PUBLIC] ?? ''),
            Relay::FOLLOW_ACTOR => (string) ($options[Relay::FOLLOW_ACTOR] ?? ''),
        ];

        foreach ($styles as $value => $text) {
            $option = new SelectOption();
            $option -> value = $value;
            $option -> contents[] = $text;

            if ($value === Relay::FOLLOW_PUBLIC) {
                $option -> attributes['selected'] = 'selected';
            }

            $select -> addContent($option);
        }

        $this -> addContent($select);
        $this -> addContent(new Paragraph((string) ($words['retryNotice'] ?? '')));

        return parent::toDOM();
    }
}
