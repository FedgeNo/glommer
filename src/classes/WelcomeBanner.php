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
        $heading = new Heading2();
        $heading -> contents[] = 'Welcome to ' . Config::get('siteTitle');
        $this -> addContent($heading);

        foreach (self::paragraphs() as $text) {
            $paragraph = new Paragraph();
            $paragraph -> contents[] = $text;
            $this -> addContent($paragraph);
        }

        $more = new Paragraph();
        $more -> contents[] = 'There is more in ';
        $more -> contents[] = new Anchor(ServerURL::absolute('/help/'), 'the help pages');
        $more -> contents[] = ', including how to move an account here from elsewhere.';
        $this -> addContent($more);

        $actions = new WelcomeBannerActions();

        $checkbox = new CheckboxField('welcomeDismissed', 'Don\'t show this again');
        $actions -> addContent($checkbox);
        $actions -> addContent(new WelcomeBannerDismissButton());

        $this -> addContent($actions);

        return parent::toDOM();
    }

    /**
     * What somebody needs to know to use the place, shortest first. Each is
     * one idea, because a wall of text at the top of the feed is a thing
     * people close without reading.
     *
     * @return string[]
     */
    private static function paragraphs(): array
    {
        return [
            'Write something in the box below and it goes out to your feed. Anyone can reply, and a reply is just a post with a parent, so conversations nest as deep as they need to.',
            'Add people as friends and their posts join your feed. The Global feed shows everything written here, which is the place to find somebody to add.',
            'This site is part of the Fediverse: you can follow accounts on Mastodon and other servers by their full handle, and what you post reaches the people who follow you there. Search for a handle like @someone@example.social and this server will go and find them.',
            'Tag a post with #hashtags and it turns up on that tag\'s page, and in Trending if enough people are writing about it.',
            'Messages between members are end-to-end encrypted - the server stores ciphertext it cannot read. Turn that on in Settings.',
            'You do not have to post straight away: save a draft, or set a time and it publishes itself. Both live under Drafts & Scheduled.',
        ];
    }
}
