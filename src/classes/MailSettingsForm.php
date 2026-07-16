<?php

declare(strict_types=1);

/**
 * The admin Site Settings form for outgoing mail: the "from" address/name,
 * plus the SMTP relay. The SMTP password is write-only, never rendered back,
 * and a blank submit leaves it unchanged - the same treatment as the
 * Turnstile/Google Auth secrets. Everything else IS shown (nothing sensitive
 * about it), matching the Turnstile site key/Google client ID being shown
 * too. A blank "from" address submit also leaves the stored value unchanged
 * (not write-only for the same reason as the password - it's just that a
 * blank address would break every subsequent email, unlike a blank host,
 * which is a valid state that falls back to PHP's mail()).
 */
class MailSettingsForm extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 MailSettingsForm';

    public function toDOM(): \DOMElement
    {
        $this -> action = ServerURL::absolute('/admin/settings');
        $this -> method = 'POST';

        $fields = new Fieldset('Outgoing mail');

        $from_address = new InputField('mailFromAddress', 'From address', 'email', 'No email can be sent until this is set', 255);
        $from_address -> value = (string) Settings::get(Mailer::FROM_ADDRESS_SETTING, '');
        $from_address -> autocomplete = 'off';
        $from_address -> labelVisible = true;
        $fields -> addContent($from_address);

        $from_name = new InputField('mailFromName', 'From name', 'text', 'From name', 100);
        $from_name -> value = (string) Settings::get(Mailer::FROM_NAME_SETTING, '');
        $from_name -> autocomplete = 'off';
        $from_name -> labelVisible = true;
        $fields -> addContent($from_name);

        $host = new InputField('smtpHost', 'SMTP host', 'text', 'SMTP host', 255);
        $host -> value = (string) Settings::get(Mailer::SMTP_HOST_SETTING, '');
        $host -> autocomplete = 'off';
        $host -> labelVisible = true;
        $fields -> addContent($host);

        $port = new InputField('smtpPort', 'SMTP port', 'text', 'SMTP port', 5);
        $port -> value = (string) Settings::get(Mailer::SMTP_PORT_SETTING, '587');
        $port -> autocomplete = 'off';
        $port -> labelVisible = true;
        $fields -> addContent($port);

        $username = new InputField('smtpUsername', 'SMTP username', 'text', 'SMTP username', 255);
        $username -> value = (string) Settings::get(Mailer::SMTP_USERNAME_SETTING, '');
        $username -> autocomplete = 'off';
        $username -> labelVisible = true;
        $fields -> addContent($username);

        $password_is_set = (string) Settings::get(Mailer::SMTP_PASSWORD_SETTING, '') !== '';
        $password_placeholder = $password_is_set
            ? 'Password is set - leave blank to keep it'
            : 'SMTP password';
        $password = new InputField('smtpPassword', 'SMTP password', 'password', $password_placeholder, 255);
        $password -> autocomplete = 'off';
        $password -> labelVisible = true;
        $fields -> addContent($password);

        $encryption = Settings::get(Mailer::SMTP_ENCRYPTION_SETTING, 'tls');

        $encryption_label = new Label();
        $encryption_label -> for = 'smtpEncryption';
        $encryption_label -> contents[] = 'Encryption';
        $fields -> addContent($encryption_label);

        $encryption_select = new Select();
        $encryption_select -> name = 'smtpEncryption';
        $encryption_select -> id = 'smtpEncryption';

        foreach (['tls' => 'TLS (STARTTLS, usual on port 587)', 'ssl' => 'SSL (implicit TLS, usual on port 465)', 'none' => 'None'] as $value => $text) {
            $option = new SelectOption();
            $option -> value = $value;
            $option -> contents[] = $text;

            if ($value === $encryption) {
                $option -> attributes['selected'] = 'selected';
            }

            $encryption_select -> addContent($option);
        }

        $fields -> addContent($encryption_select);

        $this -> contents[] = $fields;

        $this -> contents[] = new Paragraph('Leave the SMTP host blank to send via PHP\'s mail() instead (not recommended - see README\'s deliverability section). No email can be sent at all until a "from" address is set.');

        $this -> contents[] = new SubmitButton('Save');

        return parent::toDOM();
    }
}
