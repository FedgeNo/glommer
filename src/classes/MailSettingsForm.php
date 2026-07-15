<?php

declare(strict_types=1);

/**
 * The admin Site Settings form for the outgoing SMTP relay. The password is
 * write-only, never rendered back, and a blank submit leaves it unchanged -
 * the same treatment as the Turnstile/Google Auth secrets. Host/port/
 * username/encryption ARE shown (nothing sensitive about them), matching the
 * Turnstile site key/Google client ID being shown too.
 */
class MailSettingsForm extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 MailSettingsForm';

    public function toDOM(): \DOMElement
    {
        $this -> action = ServerURL::absolute('/admin/settings');
        $this -> method = 'POST';

        $fields = new Fieldset('Outgoing mail (SMTP relay)');

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
        $password = new InputField('smtpPassword', 'SMTP password', 'text', $password_placeholder, 255);
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

        $this -> contents[] = new Paragraph('Leave the host blank to send via PHP\'s mail() instead (not recommended - see README\'s deliverability section). The "From" address is set in .env, not here (it doubles as the Let\'s Encrypt registration email during setup).');

        $this -> contents[] = new SubmitButton('Save');

        return parent::toDOM();
    }
}
