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

            $this -> addContent(new NavDropdown($brand, [
                new Anchor(ServerURL::absolute('/friends-feed'), 'Friends Feed'),
                new Anchor(ServerURL::absolute('/users/' . $current_user -> username . '/friends'), 'Friends'),
                new Anchor(ServerURL::absolute('/users/'), 'Users'),
                new Anchor(ServerURL::absolute('/tags/'), 'Tags'),
                new Anchor(ServerURL::absolute('/trending-topics'), 'Trending'),
                new Anchor(ServerURL::absolute('/search'), 'Search'),
                new Anchor(ServerURL::absolute('/messages/'), 'Messages'),
                new Anchor(ServerURL::absolute('/bookmarks'), 'Bookmarks'),
                new Anchor(ServerURL::absolute('/help/'), 'Help'),
            ]));
            $recent_notifications = Notification::rowsForUser((int) $current_user -> userId, 5);
            $site_links -> addContent(new NotificationsNavLink($recent_notifications['rows'], $current_user -> lastNotificationId));

            $account_menu_links = [
                new Anchor(ServerURL::absolute('/settings'), 'Settings'),
                new LogoutForm(),
            ];

            if (Auth::canModerate()) {
                $account_menu_links[] = new Anchor(ServerURL::absolute('/admin/reports'), 'Reports');
                $account_menu_links[] = new Anchor(ServerURL::absolute('/admin/banned'), 'Banned Users');
            }

            // Site-wide settings (e.g. the Turnstile keys) are the primary
            // admin's alone, not general moderators'.
            if (Auth::id() === 1) {
                $account_menu_links[] = new Anchor(ServerURL::absolute('/admin/settings'), 'Site Settings');
            }

            $account_links -> addContent(new NavDropdown(
                new Anchor(ServerURL::absolute('/users/' . $current_user -> username . '/'), 'Logged In As ' . ($current_user -> displayName ?? $current_user -> username)),
                $account_menu_links
            ));
        } else {
            // Logged-out visitors get the same main menu, but only the items
            // that don't need an account: the public Tags directory and Help.
            $this -> addContent(new NavDropdown($brand, [
                new Anchor(ServerURL::absolute('/tags/'), 'Tags'),
                new Anchor(ServerURL::absolute('/trending-topics'), 'Trending'),
                new Anchor(ServerURL::absolute('/help/'), 'Help'),
            ]));
            $account_links -> addContent(new Anchor(ServerURL::absolute('/login'), 'Log in'));
            $account_links -> addContent(new Anchor(ServerURL::absolute('/signup'), 'Sign up'));
        }

        $this -> addContent($site_links);
        $this -> addContent($account_links);

        return parent::toDOM();
    }
}
