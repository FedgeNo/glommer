<?php

declare(strict_types=1);

/**
 * The Settings control for browser push. A button rather than a stored
 * preference: whether this browser is subscribed is the browser's own fact
 * (its Notification permission, its PushManager subscription), not something
 * the server can set - so the toggle acts on the browser and reflects it,
 * and PushNotificationSetting.js wires it. Absent entirely when the server
 * has no VAPID keypair, since there is nothing to subscribe to.
 */
class PushNotificationSetting extends Div
{
    public ?string $class = 'PushNotificationSetting';
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {
        $this -> addContent(new Paragraph(
            'Get notifications on this device even when the site isn\'t open. This is a per-browser choice - turn it on wherever you want to be reached.'
        ));

        $button = new ButtonButton();
        $button -> class = 'PushSubscribeButton';
        // The real label and disabled state are set by the script once it has
        // read the browser's actual subscription; this is the pre-JS resting
        // text, and what a no-JS visitor is left with.
        $button -> contents[] = 'Enable on this device';
        $this -> addContent($button);

        return parent::toDOM();
    }
}
