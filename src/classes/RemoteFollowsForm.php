<?php

declare(strict_types=1);

class RemoteFollowsForm extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 RemoteFollowsForm';

    /** @param array<int, array{displayName: string, status: string}> $currentFollows */
    public function __construct(private readonly array $currentFollows)
    {
        parent::__construct();
    }

    public function toDOM(): \DOMElement
    {
        $this -> action = ServerURL::absolute('/api/follow-remote');
        $this -> method = 'POST';

        $fields = new Fieldset('Follow Fediverse accounts');
        $fields -> addContent(new Notice('Paste one or more handles, e.g. @user@example.social - any separator between them works.'));

        $textarea = new TextareaField('handles', 'Fediverse handles to follow');
        $textarea -> maxLength = 8192;
        $fields -> addContent($textarea);

        $this -> contents[] = $fields;
        $this -> contents[] = new SubmitButton('Follow');

        if ($this -> currentFollows !== []) {
            $list = new Div();
            $list -> class = 'RemoteFollowsList d-flex flex-column gap-1';

            foreach ($this -> currentFollows as $follow) {
                $item = new Div();
                $item -> class = 'd-flex gap-2 align-items-center';
                $item -> contents[] = $follow['displayName'];

                $status = new Span();
                $status -> class = 'muted text-sm';
                $status -> contents[] = $follow['status'];
                $item -> contents[] = $status;

                $list -> contents[] = $item;
            }

            $this -> contents[] = $list;
        }

        return parent::toDOM();
    }
}
