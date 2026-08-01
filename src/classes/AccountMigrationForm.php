<?php

declare(strict_types=1);

/**
 * Moving an account to another server, and declaring the accounts allowed to
 * move onto this one.
 *
 * Both halves are here because a migration needs both, and putting them apart
 * would leave someone doing one and wondering why nothing happened. Followers
 * move; posts do not, and the form says so rather than letting that be a
 * surprise afterwards.
 */
class AccountMigrationForm extends FormForm
{
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {
        $user = Auth::user();
        $moved_to = is_string($user -> movedToURI) ? $user -> movedToURI : '';

        $fields = new Fieldset('Move to another server');

        if ($moved_to !== '') {
            $fields -> addContent(new Notice('This account has moved to ' . $moved_to . '. Your followers were asked to follow you there.'));
        }

        $fields -> addContent(new Paragraph('Your followers are asked to follow you at the new account. Your posts stay here - object addresses belong to the server that made them, so they cannot be taken along.'));

        $fields -> addContent(new Paragraph('The account you are moving to has to list this one under "also known as" first. Your address here is ' . ActivityPubActor::uriFor($user) . '.'));

        $destination = new InputField('movedTo', 'Move to', 'text', 'https://example.social/users/you', 255);
        $destination -> labelVisible = true;
        $destination -> value = $moved_to;
        $fields -> addContent($destination);

        $this -> contents[] = $fields;

        $aliases = new Fieldset('Also known as');

        $aliases -> addContent(new Paragraph('Accounts elsewhere that are also you. Listing one here lets that account move to this one - it is the permission, not the move itself. One address per line.'));

        $alias_field = new TextareaField('alsoKnownAs', 'Your other accounts', 'https://example.social/users/you', 2000);
        $alias_field -> value = implode("\n", ActivityPubMove::aliasesFor($user));
        $aliases -> addContent($alias_field);

        $this -> contents[] = $aliases;
        $this -> contents[] = new SubmitButton('Save');

        return parent::toDOM();
    }
}
