<?php

declare(strict_types=1);

class MainNavigation extends HTMLObject
{
    public string $tagName = 'div';
    public ?string $class = 'MainNavigation d-flex gap-4';

    public function toDOM(): \DOMElement
    {
        $brand = new Anchor(ServerURL::absolute('/'), Config::get('siteTitle'));
        $brand -> class = 'NavBrand';

        $site_links = new Div();
        $site_links -> class = 'd-flex gap-4';

        $account_links = new Div();
        $account_links -> class = 'd-flex gap-4 ms-auto NavAccount';

        if (Auth::check()) {
            $current_user = Auth::user();

            $main_menu_links = [
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

            $this -> addContent(new NavDropdown($brand, $main_menu_links));
            $site_links -> addContent(new NotificationsNavLink((int) $current_user -> userId, (int) $current_user -> lastNotificationId));

            $account_menu_links = [
                new Anchor(ServerURL::absolute('/settings'), 'Settings'),
                new LogoutForm(),
            ];

            if (Auth::canModerate()) {
                $account_menu_links[] = new Anchor(ServerURL::absolute('/admin/reports'), 'Reports');
                $account_menu_links[] = new Anchor(ServerURL::absolute('/admin/banned'), 'Banned Users');
                $account_menu_links[] = new Anchor(ServerURL::absolute('/admin/banned-entities'), 'Banned Entities');
            }

            // Site-wide settings (e.g. the Turnstile keys) are the primary
            // admin's alone, not general moderators'.
            if (Auth::id() === 1) {
                $account_menu_links[] = new Anchor(ServerURL::absolute('/admin/settings'), 'Site Settings');
            }

            $account_links -> addContent(new NavDropdown(
                new Anchor(ServerURL::absolute('/users/' . $current_user -> slug . '/'), 'Logged In As ' . ($current_user -> title ?? $current_user -> slug)),
                $account_menu_links
            ));
        } else {
            // Logged-out visitors get the same main menu, but only the items
            // that don't need an account: the public Tags directory and Help.
            $main_menu_links = [
                new Anchor(ServerURL::absolute('/tags/'), 'Tags'),
                new Anchor(ServerURL::absolute('/trending-topics'), 'Trending'),
                new Anchor(ServerURL::absolute('/help/'), 'Help'),
                new Anchor(ServerURL::absolute('/about'), 'About'),
            ];

            $this -> addContent(new NavDropdown($brand, $main_menu_links));

            $account_menu_links = [
                new Anchor(ServerURL::absolute('/login'), 'Log in'),
                new Anchor(ServerURL::absolute('/signup'), 'Sign up'),
            ];

            foreach ($account_menu_links as $link) {
                $account_links -> addContent($link);
            }
        }

        $this -> addContent($site_links);
        $this -> addContent($account_links);

        // Mobile: the two hover-flyouts above (brand's main menu, the account
        // menu) are hidden by CSS below the nav breakpoint - hover doesn't
        // work well on touch, and a position:absolute flyout can't scroll
        // independently of the page. This hamburger + panel is the mobile
        // replacement: one tap-toggled, independently-scrollable list
        // holding every link from BOTH flyouts, same PHP objects reused (see
        // HTMLObject::toDOM() - it builds a fresh DOM element per call, so
        // the same Anchor/LogoutForm instance can render into two places).
        $this -> addContent(new NavHamburgerButton());

        $mobile_menu = new Div();
        $mobile_menu -> class = 'MobileNavMenu';

        foreach (array_merge($main_menu_links, $account_menu_links) as $link) {
            $mobile_menu -> addContent($link);
        }

        $this -> addContent($mobile_menu);

        return parent::toDOM();
    }
}
