<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Applies admin-entered SMTP settings (Settings → Mail) over the file-based mail
 * config at boot. If nothing is configured, the .env config stands untouched.
 * The stored password is encrypted at rest.
 */
class MailConfig
{
    public const KEY = 'mail';

    /** @return array<string,mixed> the stored settings, password blanked */
    public static function current(): array
    {
        $s = static::raw();
        $s['password'] = ($s['password'] ?? '') !== '' ? '********' : '';

        return $s;
    }

    /** @return array<string,mixed> raw stored settings, password decrypted */
    public static function raw(): array
    {
        $defaults = [
            'host' => '', 'port' => 587, 'username' => '', 'password' => '',
            'encryption' => 'tls', 'from_address' => '', 'from_name' => config('app.name'),
        ];

        try {
            $stored = (array) (Setting::get(self::KEY) ?? []);
        } catch (Throwable) {
            $stored = [];
        }

        $merged = array_merge($defaults, $stored);

        if (($merged['password'] ?? '') !== '') {
            try {
                $merged['password'] = Crypt::decryptString($merged['password']);
            } catch (Throwable) {
                // leave as-is (was stored plain or key rotated)
            }
        }

        return $merged;
    }

    /** @param array<string,mixed> $input password '' or '********' means "keep existing" */
    public static function save(array $input): void
    {
        $existing = static::raw();

        $password = (string) ($input['password'] ?? '');
        if ($password === '' || $password === '********') {
            $password = (string) $existing['password'];
        }

        Setting::put(self::KEY, [
            'host' => trim((string) ($input['host'] ?? '')),
            'port' => (int) ($input['port'] ?? 587),
            'username' => trim((string) ($input['username'] ?? '')),
            'password' => $password !== '' ? Crypt::encryptString($password) : '',
            'encryption' => in_array($input['encryption'] ?? 'tls', ['tls', 'ssl', 'none'], true) ? $input['encryption'] : 'tls',
            'from_address' => trim((string) ($input['from_address'] ?? '')),
            'from_name' => trim((string) ($input['from_name'] ?? config('app.name'))),
        ]);
    }

    public static function configured(): bool
    {
        return static::raw()['host'] !== '';
    }

    /** Push the stored settings into the live config. Safe to call every boot. */
    public static function apply(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $s = static::raw();
        if ($s['host'] === '') {
            return;
        }

        $scheme = match ($s['encryption']) {
            'ssl' => 'smtps',
            'none' => 'smtp',
            default => null, // tls -> STARTTLS, transport decides
        };

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $s['host'],
            'mail.mailers.smtp.port' => $s['port'],
            'mail.mailers.smtp.username' => $s['username'] ?: null,
            'mail.mailers.smtp.password' => $s['password'] ?: null,
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.mailers.smtp.encryption' => $s['encryption'] === 'none' ? null : $s['encryption'],
        ]);

        if ($s['from_address'] !== '') {
            config([
                'mail.from.address' => $s['from_address'],
                'mail.from.name' => $s['from_name'] ?: config('app.name'),
            ]);
        }
    }
}
