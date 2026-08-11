<?php

declare(strict_types=1);

/**
 * The introduction a new member meets on the home feed: what this site is and
 * how the parts of it fit together.
 *
 * It stays until somebody says they are done with it. Dismissing without
 * ticking the box only puts it away for the moment, so a person who closed it
 * before finishing gets it back - and the one who ticked never sees it again,
 * which is the flag on their row (Users.welcomeDismissed).
 *
 * Written for somebody who has just arrived and does not yet know that posts
 * here can reach other servers, so it says that plainly rather than assuming
 * the word "Fediverse" means anything to them.
 */
class WelcomeBanner extends Section
{
    public ?string $class = 'WelcomeBanner';

    /** Whether this member should be shown it at all. */
    public static function isDue(?User $user): bool
    {
        return $user !== null && $user -> welcomeDismissed === 0;
    }

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);
        $greeting = $words['heading'] ?? [];

        $heading = new Heading2();
        $heading -> contents[] = (string) ($greeting['before'] ?? '');
        $heading -> contents[] = Config::get('siteTitle');
        $heading -> contents[] = (string) ($greeting['after'] ?? '');
        $this -> addContent($heading);

        foreach ((array) ($words['paragraphs'] ?? []) as $text) {
            $paragraph = new Paragraph();
            $paragraph -> contents[] = (string) $text;
            $this -> addContent($paragraph);
        }

        $more = $words['more'] ?? [];

        $more_paragraph = new Paragraph();
        $more_paragraph -> contents[] = (string) ($more['before'] ?? '');
        $more_paragraph -> contents[] = new Anchor(ServerURL::absolute('/help/'), (string) ($more['link'] ?? ''));
        $more_paragraph -> contents[] = (string) ($more['after'] ?? '');
        $this -> addContent($more_paragraph);

        $actions = new WelcomeBannerActions();

        $checkbox = new CheckboxField('welcomeDismissed', (string) ($words['dontShowAgain'] ?? ''));
        $actions -> addContent($checkbox);
        $actions -> addContent(new WelcomeBannerDismissButton());

        $this -> addContent($actions);

        return parent::toDOM();
    }
}
