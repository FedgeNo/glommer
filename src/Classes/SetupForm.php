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
        $current_url = (($_SERVER['HTTPS'] ?? '') !== '' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'example.com');

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

        $this -> contents[] = new SubmitButton('Set Up');

        return parent::toDOM();
    }
}
