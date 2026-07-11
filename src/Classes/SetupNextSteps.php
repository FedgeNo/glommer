<?php

declare(strict_types=1);

/**
 * The web setup wizard's "setup finished" checklist - the manual steps that
 * remain after .env has been written, in the order to do them.
 */
class SetupNextSteps extends OrderedList
{
    public ?string $class = 'd-flex flex-column gap-2 SetupNextSteps';

    public function toDOM(): \DOMElement
    {
        $steps = [
            'Restore the project root\'s normal permissions (it was made web-server-writable so this step could write .env): run `chmod 755 ' . realpath(__DIR__ . '/../..') . '` on the server.',
            'Restart the WebSocket server so it picks up the freshly generated WS_SECRET in .env: `systemctl --user restart glommer-websocket` (or however you\'re running bin/websocket-server.php).',
            'Reload this page and sign up - the first account created becomes the site\'s administrator.',
        ];

        foreach ($steps as $step) {
            $item = new ListItem();
            $item -> contents[] = $step;
            $this -> contents[] = $item;
        }

        return parent::toDOM();
    }
}
