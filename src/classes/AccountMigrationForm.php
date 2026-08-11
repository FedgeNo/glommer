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

    // Defaults to the signed-in member, so the page that actually shows this
    // form (user-settings.php) needs no change; a test can hand in a
    // fabricated User instead of standing up a session to get one from Auth.
    public function __construct(private readonly ?User $user = null)
    {
        parent::__construct();
    }

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);
        $user = $this -> user ?? Auth::user();
        $moved_to = is_string($user -> movedToURI) ? $user -> movedToURI : '';

        $fields = new Fieldset((string) ($words['legend'] ?? ''));

        if ($moved_to !== '') {
            $fields -> addContent(new Notice(str_replace('{destination}', $moved_to, (string) ($words['movedNotice'] ?? ''))));
        }

        $fields -> addContent(new Paragraph((string) ($words['explanation'] ?? '')));

        $fields -> addContent(new Paragraph(str_replace('{address}', ActivityPubActor::uriFor($user), (string) ($words['addressNotice'] ?? ''))));

        $destination = new InputField('movedTo', (string) ($words['movedToLabel'] ?? ''), 'text', (string) ($words['movedToPlaceholder'] ?? ''), 255);
        $destination -> labelVisible = true;
        $destination -> value = $moved_to;
        $fields -> addContent($destination);

        $this -> contents[] = $fields;

        $aliases = new Fieldset((string) ($words['aliasesLegend'] ?? ''));

        $aliases -> addContent(new Paragraph((string) ($words['aliasesExplanation'] ?? '')));

        $alias_field = new TextareaField('alsoKnownAs', (string) ($words['aliasesLabel'] ?? ''), (string) ($words['aliasesPlaceholder'] ?? ''), 2000);
        $alias_field -> value = implode("\n", ActivityPubMove::aliasesFor($user));
        $aliases -> addContent($alias_field);

        $this -> contents[] = $aliases;
        $this -> contents[] = new SubmitButton((string) ($words['submit'] ?? ''));

        return parent::toDOM();
    }
}
