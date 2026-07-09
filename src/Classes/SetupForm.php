<?php

declare(strict_types=1);

class SetupForm extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 SetupForm';

    public function toDOM(): \DOMElement
    {
        $this -> action = URL::absolute('/');
        $this -> method = 'POST';

        $site_fields = new Fieldset('Site');
        $site_fields -> addContents(new InputField('siteURL', 'Site URL', 'text', 'https://example.com', 255));
        $site_fields -> addContents(new InputField('siteTitle', 'Site title', 'text', 'Site title', 100));
        $site_fields -> addContents(new InputField('mailFromAddress', 'Mail from address', 'email', 'noreply@example.com', 255));
        $site_fields -> addContents(new InputField('mailFromName', 'Mail from name', 'text', 'Mail from name', 100));
        $this -> contents[] = $site_fields;

        $db_fields = new Fieldset('Database');
        $db_fields -> addContents(new InputField('dbHost', 'Database host', 'text', '127.0.0.1', 255));
        $db_fields -> addContents(new InputField('dbPort', 'Database port', 'text', '3306', 5));
        $db_fields -> addContents(new InputField('dbDatabase', 'Database name', 'text', 'glommer', 64));
        $db_fields -> addContents(new InputField('adminUsername', 'Database admin username', 'text', 'Database admin username', 255));
        $db_fields -> addContents(new InputField('adminPassword', 'Database admin password', 'password', 'Database admin password'));
        $this -> contents[] = $db_fields;

        $submit = new Button();
        $submit -> type = 'submit';
        $submit -> class = 'Btn align-self-start';
        $submit -> contents[] = 'Set Up';
        $this -> contents[] = $submit;

        return parent::toDOM();
    }
}
