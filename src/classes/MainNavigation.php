<?php

declare(strict_types=1);

class MainNavigation extends Nav
{
    public ?string $class = 'MainNavigation';
    public array $mixins = ['d-flex', 'gap-4'];

    public function toDOM(): \DOMElement
    {
        // Checkbox-hack toggle: the hamburger <label> flips this hidden checkbox,
        // and the CSS below the nav breakpoint reveals the stacked menu while
        // it's checked - so mobile navigation is pure CSS, no JS. Above the
        // breakpoint both are hidden and the desktop hover-flyouts take over.
        $toggle = new CheckboxInput();
        $toggle -> id = 'NavToggle';
        $toggle -> class = 'NavToggle';
        $this -> addContent($toggle);

        $hamburger = new Label();
        $hamburger -> for = 'NavToggle';
        $hamburger -> class = 'NavHamburger';
        $hamburger -> attributes['aria-label'] = 'Menu';

        for ($i = 0; $i < 3; $i++) {
            $bar = new NavHamburgerBar();
            $hamburger -> addContent($bar);
        }

        // Sits beside the hamburger, and only exists on a narrow screen: with
        // the menu closed, the marks on Notifications and Messages are inside
        // something nobody can see.
        $this -> addContent(new NavAlertDot(NavAlertDot::anythingNewFor(Auth::user())));

        $this -> addContent($hamburger);

        $brand = new NavBrand(ServerURL::absolute('/'), Config::get('siteTitle'));

        // On the site's own name, because it is on every page and is where the
        // eye lands first - so a waiting message is visible without opening
        // the menu the Messages link lives in.
        $unread_messages = MessageDot::unreadFor(Auth::user());
        $brand -> addContent(new MessageDot($unread_messages));

        $site_links = new Div();
        $site_links -> mixins = ['d-flex', 'gap-4'];

        $account_links = new NavAccount();
        $account_links -> mixins = ['d-flex', 'gap-4', 'ms-auto'];

        // Desktop: a hover-flyout of the main menu hangs off the brand. Mobile:
        // the same links render inline inside the toggled menu - one set of link
        // instances, no duplicate mobile list.
        $this -> addContent(new NavDropdown($brand, $this -> mainMenuLinks()));

        if (Auth::check()) {
            $current_user = Auth::user();

            $site_links -> addContent(new NotificationsNavLink((int) $current_user -> userId, (int) $current_user -> lastNotificationId));

            $account_label = new NavAccountLabel();
            $account_label -> addContent('Logged In As ' . ($current_user -> title ?: $current_user -> slug));

            $account_trigger = new Anchor(ServerURL::absolute('/users/' . $current_user -> slug . '/'));
            $account_trigger -> addContent($account_label);

            $account_links -> addContent(new NavDropdown($account_trigger, $this -> accountMenuLinks()));
        } else {
            // Logged-out visitors get Log in / Sign up as plain links.
            $account_links -> addContents($this -> accountMenuLinks());
        }

        $this -> addContent($site_links);
        $this -> addContent($account_links);

        return parent::toDOM();
    }

    /**
     * The brand/main-menu links. Logged-out visitors get only the items that
     * don't need an account.
     *
     * @return Anchor[]
     */
    private function mainMenuLinks(): array
    {
        if (!Auth::check()) {
            return [
                new Anchor(ServerURL::absolute('/tags/'), 'Tags'),
                new Anchor(ServerURL::absolute('/trending-topics'), 'Trending'),
                new Anchor(ServerURL::absolute('/map'), 'Map'),
                new Anchor(ServerURL::absolute('/help/'), 'Help'),
                new Anchor(ServerURL::absolute('/about'), 'About'),
            ];
        }

        $current_user = Auth::user();

        $links = [
            new Anchor(ServerURL::absolute('/friends-feed'), 'Friends Feed'),
            new Anchor(ServerURL::absolute('/users/'), 'Users'),
            new Anchor(ServerURL::absolute('/tags/'), 'Tags'),
            new Anchor(ServerURL::absolute('/trending-topics'), 'Trending'),
            new Anchor(ServerURL::absolute('/map'), 'Map'),
            new Anchor(ServerURL::absolute('/nearby'), 'Nearby'),
            new Anchor(ServerURL::absolute('/search'), 'Search'),
            $this -> messagesLink(),
            new Anchor(ServerURL::absolute('/bookmarks'), 'Bookmarks'),
            new Anchor(ServerURL::absolute('/help/'), 'Help'),
            new Anchor(ServerURL::absolute('/about'), 'About'),
        ];

        // Only where a relay is actually subscribed: on a server that never
        // joins one the feed can only ever be empty, and a permanent link to
        // it is furniture in everybody's way.
        if (Relay::anySubscribed()) {
            array_splice($links, 1, 0, [new Anchor(ServerURL::absolute('/relay-feed'), 'Relay Feed')]);
        }

        return $links;
    }

    /**
     * The Messages link, carrying the same unread mark the brand does - the
     * brand says something is waiting, this says where.
     */
    private function messagesLink(): HTMLObject
    {
        $link = new Anchor(ServerURL::absolute('/messages/'), 'Messages');
        $link -> addContent(new MessageDot(MessageDot::unreadFor(Auth::user())));

        return $link;
    }

    /**
     * The account-menu links.
     *
     * @return HTMLObject[]
     */
    private function accountMenuLinks(): array
    {
        if (!Auth::check()) {
            return [
                new Anchor(ServerURL::absolute('/login'), 'Log in'),
                new Anchor(ServerURL::absolute('/signup'), 'Sign up'),
            ];
        }

        // Friends leads, right under the "Logged In As" header - it is about
        // the person named there. Then the settings pages together, and
        // logging out last - it is the one thing here that ends the session
        // rather than opening something, and it does not want to be in the
        // middle of a list being scanned.
        $links = [
            new Anchor(ServerURL::absolute('/users/' . Auth::user() -> slug . '/friends'), 'Friends'),
            new Anchor(ServerURL::absolute('/drafts'), 'Drafts & Scheduled'),
            new Anchor(ServerURL::absolute('/settings'), 'Settings'),
        ];

        // Everything a moderator does now gathers on one page, which either
        // holds the tool or points at it.
        if (Auth::canModerate()) {
            $links[] = new Anchor(ServerURL::absolute('/admin/mod-settings'), 'Mod Settings');
        }

        // Site-wide settings (the Turnstile keys, the relays this server
        // subscribes to) are the primary admin's alone, not general
        // moderators'.
        if (Auth::id() === 1) {
            $links[] = new Anchor(ServerURL::absolute('/admin/settings'), 'Admin Settings');
        }

        $links[] = new LogoutForm();

        return $links;
    }
}
