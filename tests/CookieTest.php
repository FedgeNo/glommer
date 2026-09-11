<?php

declare(strict_types=1);

class CookieTest extends TestCase
{
    public function testHTTPSReadsOnlyHostPrefixedCookies(): void
    {
        $previous_server = $_SERVER;
        $previous_cookies = $_COOKIE;

        try {
            $_SERVER['HTTPS'] = 'on';
            $_COOKIE = [];

            foreach (['PHPSESSID', 'rememberToken', 'CSRF-TOKEN', 'APP-CONFIG'] as $name) {
                $_COOKIE[$name] = 'untrusted';
                $this -> assertSame('__Host-' . $name, Cookie::name($name));
                $this -> assertNull(Cookie::get($name), 'HTTPS must not fall back to the unprefixed cookie');

                $_COOKIE['__Host-' . $name] = 'trusted';
                $this -> assertSame('trusted', Cookie::get($name));
            }

            $_COOKIE['__Host-rememberToken'] = 'current:validator';
            $_COOKIE['rememberToken'] = 'legacy:validator';
            $this -> assertSame('current', RememberToken::currentSelector());

            unset($_COOKIE['__Host-rememberToken']);
            $this -> assertNull(RememberToken::currentSelector());
            RememberToken::loginFromCookie();

            $_COOKIE['__Host-rememberToken'] = ['unexpected'];
            $this -> assertNull(Cookie::get('rememberToken'));
        } finally {
            $_SERVER = $previous_server;
            $_COOKIE = $previous_cookies;
        }
    }

    public function testSetupHTTPAndTrustedProxyUseTheCorrectCookieNames(): void
    {
        $previous_server = $_SERVER;
        $previous_cookies = $_COOKIE;

        try {
            $_SERVER = ['HTTPS' => 'off', 'REMOTE_ADDR' => '127.0.0.1'];
            $_COOKIE = ['CSRF-TOKEN' => 'setup-token'];
            $this -> assertSame('PHPSESSID', Cookie::name('PHPSESSID'));
            $this -> assertSame('setup-token', Cookie::get('CSRF-TOKEN'));

            $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
            $this -> assertSame('__Host-PHPSESSID', Cookie::name('PHPSESSID'));
            $this -> assertNull(Cookie::get('CSRF-TOKEN'));
        } finally {
            $_SERVER = $previous_server;
            $_COOKIE = $previous_cookies;
        }
    }
}
