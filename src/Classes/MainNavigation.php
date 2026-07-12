<?php

declare(strict_types=1);

class MainNavigation extends HTMLObject
{
    public string $tagName = 'div';
    public ?string $class = 'MainNavigation d-flex gap-4';

    public function toDOM(): \DOMElement
    {
        $config = require __DIR__ . '/../config.php';

        $brand = new Anchor(ServerURL::absolute('/'), $config['siteTitle']);
        $brand -> class = 'NavBrand';

        $site_links = new Div();
        $site_links -> class = 'd-flex gap-4';

        $account_links = new Div();
        $account_links -> class = 'd-flex gap-4 ms-auto NavAccount';

        if (Auth::check()) {
            $current_user = Auth::user();

            $this -> addContents(new NavDropdown($brand, [
                new Anchor(ServerURL::absolute('/friends-feed'), 'Friends Feed'),
                new Anchor(ServerURL::absolute('/users/' . $current_user -> username . '/friends'), 'Friends'),
                new Anchor(ServerURL::absolute('/users/'), 'Users'),
                new Anchor(ServerURL::absolute('/search'), 'Search'),
                new Anchor(ServerURL::absolute('/messages/'), 'Messages'),
                new Anchor(ServerURL::absolute('/help/'), 'Help'),
            ]));
            $recent_notifications = Notification::rowsForUser((int) $current_user -> userId, 5);
            $site_links -> addContents(new NotificationsNavLink($recent_notifications['rows'], $current_user -> lastNotificationId));

            $account_menu_links = [
                new Anchor(ServerURL::absolute('/settings'), 'Settings'),
                new Anchor(ServerURL::absolute('/logout'), 'Log out'),
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

            $account_links -> addContents(new NavDropdown(
                new Anchor(ServerURL::absolute('/users/' . $current_user -> username . '/'), 'Logged In As ' . ($current_user -> displayName ?? $current_user -> username)),
                $account_menu_links
            ));
        } else {
            // Logged-out visitors get the same main menu, but with only the
            // Help item - everything else in it needs an account.
            $this -> addContents(new NavDropdown($brand, [
                new Anchor(ServerURL::absolute('/help/'), 'Help'),
            ]));
            $account_links -> addContents(new Anchor(ServerURL::absolute('/login'), 'Log in'));
            $account_links -> addContents(new Anchor(ServerURL::absolute('/signup'), 'Sign up'));
        }

        $this -> addContents($site_links);
        $this -> addContents($account_links);

        return parent::toDOM();
    }
}
