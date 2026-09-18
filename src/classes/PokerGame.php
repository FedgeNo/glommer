<?php

declare(strict_types=1);

class PokerGame extends GameRoom
{
    public bool $signedIn = false;

    public function toDOM(): \DOMElement
    {
        $root = Section::toDOM();
        $this -> chrome($root);
        $root -> appendChild($this -> element('p', ['class' => 'casino-eyebrow'], 'NINE SEATS · NO-LIMIT HOLD’EM'));
        $root -> appendChild($this -> element('h1', ['class' => 'casino-title'], 'The Poker Room'));
        $root -> appendChild($this -> element('p', ['class' => 'PokerIntroduction'], '200–1,000 chips at the table · 10 / 20 blinds · No rake. People play as @slugs; suit-marked opponents are bots.'));
        $root -> setAttribute('data-signed-in', $this -> signedIn ? '1' : '0');
        $status = $this -> element('p', ['class' => 'PokerStatus', 'role' => 'status'], $this -> signedIn ? 'Take a seat to find your table.' : 'Sign in to play with your @slug and avatar.');
        $root -> appendChild($status);
        if (!$this -> signedIn) $root -> appendChild($this -> element('a', ['href' => '/login', 'class' => 'PokerSignIn'], 'Sign In To Play'));
        $controls = $this -> element('div', ['class' => 'PokerControls']);
        foreach (['join' => 'Take A Seat · Up To 1,000', 'leave' => 'Leave After This Hand'] as $action => $label) {
            $button = $this -> element('button', ['type' => 'button', 'data-poker-action' => $action], $label);
            if (!$this -> signedIn) $button -> setAttribute('disabled', '');
            $controls -> appendChild($button);
        }
        $root -> appendChild($controls);
        $controls -> appendChild($this -> element('button', ['type' => 'button', 'class' => 'PokerSoundToggle', 'aria-pressed' => 'false'], 'Enable Sound'));
        $table = $this -> element('section', ['class' => 'PokerTable', 'aria-label' => 'Poker table']);
        $table -> appendChild($this -> element('div', ['class' => 'PokerScene', 'aria-hidden' => 'true']));
        $table -> appendChild($this -> element('div', ['class' => 'PokerSeats']));
        $center = $this -> element('div', ['class' => 'PokerCenter']);
        $center -> appendChild($this -> element('strong', ['class' => 'PokerPot'], 'TAKE YOUR SEAT'));
        $center -> appendChild($this -> element('div', ['class' => 'PokerBoard', 'aria-label' => 'Community cards']));
        $center -> appendChild($this -> element('span', ['class' => 'PokerStreet']));
        $center -> appendChild($this -> element('span', ['class' => 'PokerWaiting']));
        $table -> appendChild($center);
        $root -> appendChild($table);
        $form = (new PokerBetForm()) -> toDOM();
        $form -> appendChild($this -> element('p', ['class' => 'PokerTurn', 'role' => 'status'], 'Your controls appear when it is your turn.'));
        foreach (['fold' => 'Fold', 'check' => 'Check', 'call' => 'Call', 'allin' => 'All In'] as $action => $label) {
            $form -> appendChild($this -> element('button', ['type' => 'button', 'data-poker-move' => $action, 'disabled' => ''], $label));
        }
        $label = $this -> element('label', [], 'Raise To ');
        $label -> appendChild($this -> element('input', ['type' => 'number', 'name' => 'amount', 'min' => '20', 'step' => '1', 'value' => '40', 'inputmode' => 'numeric', 'disabled' => '']));
        $form -> appendChild($label);
        $form -> appendChild($this -> element('button', ['type' => 'submit', 'disabled' => ''], 'Raise'));
        $presets = $this -> element('div', ['class' => 'PokerPresets', 'aria-label' => 'Raise presets']);
        foreach (['minimum' => 'Minimum', 'half' => '½ Pot', 'pot' => 'Pot'] as $preset => $label) {
            $presets -> appendChild($this -> element('button', ['type' => 'button', 'data-poker-preset' => $preset, 'disabled' => ''], $label));
        }
        $form -> appendChild($presets);
        $form -> appendChild($this -> element('p', ['class' => 'PokerBetCost', 'aria-live' => 'polite']));
        $root -> appendChild($form);
        $root -> appendChild($this -> element('p', ['class' => 'PokerResult', 'role' => 'status']));
        $root -> appendChild($this -> element('ol', ['class' => 'PokerLog', 'aria-label' => 'Recent table actions']));
        $details = $this -> element('details', ['class' => 'PokerRules']);
        $details -> appendChild($this -> element('summary', [], 'How This Table Works'));
        $details -> appendChild($this -> element('p', [], 'Make the best five-card hand using any of your two cards and the five community cards. Fold, check, call, or raise when your seat lights up. An all-in can create side pots; you can only win chips you have matched.'));
        $details -> appendChild($this -> element('p', [], 'Each hand puts up to 1,000 wallet chips in play, with a minimum of 200. This is your betting stack, not an entry fee. Unspent chips and winnings return afterward. Stay seated to enter the next hand automatically. After ten seconds to view the result, a twenty-second matching window groups waiting people together and fills spare seats with bots. Leaving stops the next buy-in; your current hand still finishes.'));
        $details -> appendChild($this -> element('p', [], 'You have 25 seconds to act. If time runs out, you check when possible and otherwise fold. Close the page or stay away for 45 seconds and your next-hand reservation expires. Bots have their own cards and cannot see yours.'));
        $root -> appendChild($details);
        $this -> footer($root);
        $chat = $this -> element('section', ['class' => 'PokerChat']);
        $chat -> appendChild($this -> element('h2', [], 'Table Chat'));
        $chat -> appendChild($this -> element('p', [], 'Visible to people at your table. Chat changes when you move tables.'));
        $chat -> appendChild($this -> element('div', ['class' => 'PokerMessages', 'role' => 'log', 'aria-live' => 'polite', 'aria-relevant' => 'additions', 'aria-label' => 'Table chat']));
        $chat_form = (new PokerChatForm()) -> toDOM();
        $chat_label = $this -> element('label', [], 'Message ');
        $chat_label -> appendChild($this -> element('input', ['name' => 'body', 'type' => 'text', 'maxlength' => '500', 'required' => '', 'autocomplete' => 'off', 'disabled' => '']));
        $chat_form -> appendChild($chat_label);
        $chat_form -> appendChild($this -> element('button', ['type' => 'submit', 'disabled' => ''], 'Send'));
        $chat -> appendChild($chat_form);
        $root -> appendChild($chat);
        return $root;
    }
}
