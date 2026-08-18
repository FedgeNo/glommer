<?php

declare(strict_types=1);

class RemoteFollowsForm extends FormForm
{

    /** @param array<int, array{displayName: string, slug: string, status: string}> $currentFollows */
    public function __construct(private readonly array $currentFollows)
    {
        parent::__construct();
    }

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);

        $fields = new Fieldset((string) ($words['legend'] ?? ''));
        $fields -> addContent(new Notice((string) ($words['notice'] ?? '')));

        $textarea = new TextareaField('handles', (string) ($words['handlesLabel'] ?? ''));
        $textarea -> maxLength = 8192;
        $fields -> addContent($textarea);

        $this -> contents[] = $fields;
        $this -> contents[] = new SubmitButton((string) ($words['submit'] ?? ''));

        if ($this -> currentFollows !== []) {
            $list = new RemoteFollowsList();

            foreach ($this -> currentFollows as $follow) {
                $item = new RemoteFollowsItem();

                // Their profile here, which is where the Unfollow button is -
                // otherwise the only way back to someone already followed is
                // to search for a name you may not remember.
                $item -> contents[] = new Anchor(
                    ServerURL::absolute('/users/' . $follow['slug'] . '/'),
                    $follow['displayName']
                );

                $status = new RemoteFollowsStatus();
                // RemoteFollows.status is only ever 'pending' or 'accepted' -
                // a rejected follow is deleted rather than parked (see
                // ActivityPubInbox::handleReject()) - but a stray value falls
                // back to itself rather than fataling the settings page.
                $status -> contents[] = (string) (match ($follow['status']) {
                    'pending' => $words['statusPending'] ?? 'pending',
                    'accepted' => $words['statusAccepted'] ?? 'accepted',
                    default => $follow['status'],
                });
                $item -> contents[] = $status;

                $list -> contents[] = $item;
            }

            $this -> contents[] = $list;
        }

        return parent::toDOM();
    }
}
