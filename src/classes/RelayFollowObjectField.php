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
        $label = new Label();
        $label -> for = 'followObject';
        $label -> contents[] = 'Subscription style';
        $this -> addContent($label);

        $select = new Select();
        $select -> name = 'followObject';
        $select -> id = 'followObject';

        $styles = [
            Relay::FOLLOW_PUBLIC => 'Follow the public stream (what most relays expect)',
            Relay::FOLLOW_ACTOR => 'Follow the relay\'s own actor',
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
        $this -> addContent(new Paragraph('If the relay never accepts, withdraw and try the other style - some relay software only recognises one of them.'));

        return parent::toDOM();
    }
}
