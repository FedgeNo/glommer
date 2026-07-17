<?php

declare(strict_types=1);

class MainNavigation extends HTMLObject
{
    public string $tagName = 'nav';
    public ?string $class = 'MainNavigation d-flex gap-4';

    public function toDOM(): \DOMElement
    {
        $brand = new Anchor(ServerURL::absolute('/'), Config::get('siteTitle'));
        $brand -> class = 'NavBrand';

        $site_links = new Div();
        $site_links -> class = 'd-flex gap-4';

        $account_links = new Div();
        $account_links -> class = 'd-flex gap-4 ms-auto NavAccount';

        // The desktop hover-flyouts get one set of link instances; the mobile
        // menu below gets its own fresh set - the same instance can't render
        // into two places, since a rendered HTMLObject is one-shot.
        $this -> addContent(new NavDropdown($brand, $this -> mainMenuLinks()));

        if (Auth::check()) {
            $current_user = Auth::user();

            $site_links -> addContent(new NotificationsNavLink((int) $current_user -> userId, (int) $current_user -> lastNotificationId));

            $account_label = new Span();
            $account_label -> class = 'NavAccountLabel';
            $account_label -> addContent('Logged In As ' . ($current_user -> title ?: $current_user -> slug));

            $account_trigger = new Anchor(ServerURL::absolute('/users/' . $current_user -> slug . '/'));
            $account_trigger -> addContent($account_label);

            $account_links -> addContent(new NavDropdown($account_trigger, $this -> accountMenuLinks()));
        } else {
            // Logged-out visitors get Log in / Sign up as plain links, not a
            // dropdown trigger.
            $account_links -> addContents($this -> accountMenuLinks());
        }

        $this -> addContent($site_links);
        $this -> addContent($account_links);

        // Mobile: the two hover-flyouts above are hidden by CSS below the nav
        // breakpoint (hover doesn't work well on touch, and a position:absolute
        // flyout can't scroll independently of the page). This hamburger + panel
        // is the mobile replacement: one tap-toggled, independently-scrollable
        // list holding every link from BOTH flyouts, built fresh here.
        $this -> addContent(new NavHamburgerButton());

        $mobile_menu = new Div();
        $mobile_menu -> class = 'MobileNavMenu';
        $mobile_menu -> addContents(array_merge($this -> mainMenuLinks(), $this -> accountMenuLinks()));

        $this -> addContent($mobile_menu);

        return parent::toDOM();
    }

    /**
     * The brand/main-menu links, a fresh set of instances each call. Logged-out
     * visitors get only the items that don't need an account.
     *
     * @return Anchor[]
     */
    private function mainMenuLinks(): array
    {
        if (!Auth::check()) {
            return [
                new Anchor(ServerURL::absolute('/tags/'), 'Tags'),
                new Anchor(ServerURL::absolute('/trending-topics'), 'Trending'),
                new Anchor(ServerURL::absolute('/help/'), 'Help'),
                new Anchor(ServerURL::absolute('/about'), 'About'),
            ];
        }

        $current_user = Auth::user();

        return [
            new Anchor(ServerURL::absolute('/friends-feed'), 'Friends Feed'),
            new Anchor(ServerURL::absolute('/users/' . $current_user -> slug . '/friends'), 'Friends'),
            new Anchor(ServerURL::absolute('/users/'), 'Users'),
            new Anchor(ServerURL::absolute('/tags/'), 'Tags'),
            new Anchor(ServerURL::absolute('/trending-topics'), 'Trending'),
            new Anchor(ServerURL::absolute('/search'), 'Search'),
            new Anchor(ServerURL::absolute('/messages/'), 'Messages'),
            new Anchor(ServerURL::absolute('/bookmarks'), 'Bookmarks'),
            new Anchor(ServerURL::absolute('/help/'), 'Help'),
            new Anchor(ServerURL::absolute('/about'), 'About'),
        ];
    }

    /**
     * The account-menu links, a fresh set of instances each call.
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

        $links = [
            new Anchor(ServerURL::absolute('/settings'), 'Settings'),
            new LogoutForm(),
        ];

        if (Auth::canModerate()) {
            $links[] = new Anchor(ServerURL::absolute('/admin/reports'), 'Reports');
            $links[] = new Anchor(ServerURL::absolute('/admin/banned'), 'Banned Users');
            $links[] = new Anchor(ServerURL::absolute('/admin/banned-entities'), 'Banned Entities');
        }

        // Site-wide settings (e.g. the Turnstile keys) are the primary admin's
        // alone, not general moderators'.
        if (Auth::id() === 1) {
            $links[] = new Anchor(ServerURL::absolute('/admin/settings'), 'Site Settings');
        }

        return $links;
    }
}
