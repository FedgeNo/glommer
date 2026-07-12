<?php

declare(strict_types=1);

class SetupForm extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 SetupForm';

    public function toDOM(): \DOMElement
    {
        $this -> action = URL::absolute('/');
        $this -> method = 'POST';

        // Everything guessable is pre-filled: the site URL from how this
        // page is being visited right now, the database fields from the
        // standard local-MySQL defaults. All of it stays editable - these
        // are starting points the installing admin reviews, not decisions.
        // The URL is always prefilled as https:// regardless of how the setup
        // page itself was reached - an http URL is rejected on submit (HTTPS
        // is required), so prefilling the reached-by protocol would just bake
        // in an error.
        $current_url = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'example.com');

        $site_url = new InputField('siteURL', 'Site URL', 'text', 'https://example.com', 255);
        $site_url -> value = $current_url;

        $site_title = new InputField('siteTitle', 'Site title', 'text', 'Site title', 100);
        $site_title -> value = 'Glommer';

        $mail_from_address = new InputField('mailFromAddress', 'Mail from address', 'email', 'noreply@example.com', 255);
        $mail_from_address -> value = 'noreply@' . (parse_url($current_url, PHP_URL_HOST) ?: 'example.com');

        $mail_from_name = new InputField('mailFromName', 'Mail from name', 'text', 'Mail from name', 100);
        $mail_from_name -> value = 'Glommer';

        $site_fields = new Fieldset('Site');
        $site_fields -> addContents($site_url);
        $site_fields -> addContents($site_title);
        $site_fields -> addContents($mail_from_address);
        $site_fields -> addContents($mail_from_name);

        // ServerName/UseCanonicalName are proven live (a forged-Host-header
        // request against the entered site URL) wherever possible - this
        // checkbox is only consulted as a fallback when that live test comes
        // back inconclusive, mirroring bin/install.php's SERVERNAME_CONFIRMED
        // override for the same case.
        $current_host = (string) (parse_url($current_url, PHP_URL_HOST) ?: 'your-domain');
        $server_name_confirmed = new CheckboxField('serverNameConfirmed', 'I\'ve set "ServerName ' . $current_host . '" and "UseCanonicalName On" in my web server\'s config (only checked if the automated live test can\'t complete - see README.md\'s HTTPS section)');
        $site_fields -> addContents($server_name_confirmed);

        $this -> contents[] = $site_fields;

        $db_host = new InputField('DBHost', 'Database host', 'text', '127.0.0.1', 255);
        $db_host -> value = '127.0.0.1';

        $db_port = new InputField('DBPort', 'Database port', 'text', '3306', 5);
        $db_port -> value = '3306';

        $db_database = new InputField('DBDatabase', 'Database name', 'text', 'glommer', 64);
        $db_database -> value = 'glommer';

        $admin_username = new InputField('adminUsername', 'Database admin username', 'text', 'Database admin username', 255);
        $admin_username -> value = 'root';

        $db_fields = new Fieldset('Database');
        $db_fields -> addContents($db_host);
        $db_fields -> addContents($db_port);
        $db_fields -> addContents($db_database);
        $db_fields -> addContents($admin_username);
        $db_fields -> addContents(new InputField('adminPassword', 'Database admin password', 'password', 'Database admin password'));
        $this -> contents[] = $db_fields;

        // Optional: since the site is required to be https, browsers refuse a
        // plain ws:// connection to the WebSocket daemon - it needs its own
        // TLS certificate. Setup tries to generate one automatically via
        // mkcert first; these fields are only needed as a fallback if that
        // isn't possible (mkcert missing, or generation fails).
        $ws_tls_fields = new Fieldset('WebSocket TLS (optional)');
        $ws_tls_fields -> addContents(new InputField('wsTLSCert', 'Certificate path', 'text', 'Leave blank to generate automatically via mkcert', 500));
        $ws_tls_fields -> addContents(new InputField('wsTLSKey', 'Key path', 'text', 'Leave blank to generate automatically via mkcert', 500));
        $this -> contents[] = $ws_tls_fields;

        // Optional: Cloudflare Turnstile ("I am not a robot") on sign-up and
        // sign-in. Leave blank to skip - it can be set later in Site Settings.
        // Both keys are needed for it to take effect.
        $turnstile_fields = new Fieldset('Bot protection (optional)');

        $turnstile_site_key = new InputField('turnstileSiteKey', 'Cloudflare Turnstile site key', 'text', 'Leave blank to skip', 255);
        $turnstile_site_key -> autocomplete = 'off';
        $turnstile_fields -> addContents($turnstile_site_key);

        $turnstile_secret_key = new InputField('turnstileSecretKey', 'Cloudflare Turnstile secret key', 'text', 'Leave blank to skip', 255);
        $turnstile_secret_key -> autocomplete = 'off';
        $turnstile_fields -> addContents($turnstile_secret_key);

        $this -> contents[] = $turnstile_fields;

        $this -> contents[] = new SubmitButton('Set Up');

        return parent::toDOM();
    }
}
