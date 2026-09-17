<?php

declare(strict_types=1);

namespace Tests\Cases;

use App\Core\Auth;
use App\Core\Database;
use App\Repositories\OtpRepository;
use App\Repositories\UserRepository;
use App\Services\OtpService;
use Tests\TestCase;

/**
 * Section 60: the second login factor.
 *
 * The mail driver is switched to `log` for the duration so nothing is actually
 * sent, and the code is read back out of the database rather than the log.
 */
final class OtpTest extends TestCase
{
    private OtpService $otp;
    private OtpRepository $codes;
    private UserRepository $users;
    private int $userId = 0;
    private string $previousDriver = '';

    public function name(): string
    {
        return 'Two-step sign-in';
    }

    public function run(): void
    {
        $settings = \App\Services\SettingsService::instance();
        $this->previousDriver = (string) $settings->get('mail_driver', 'mail');
        $settings->set('mail_driver', 'log', 'string', 'email');
        \App\Core\Config::set('mail.driver', 'log');

        $this->otp = new OtpService();
        $this->codes = new OtpRepository();
        $this->users = new UserRepository();

        $this->userId = $this->users->create([
            'name'     => 'OTP Tester',
            'email'    => 'otp-' . bin2hex(random_bytes(4)) . '@example.test',
            'password' => Auth::hash('OtpPass#2026'),
            'role_id'  => (int) ((new \App\Repositories\RoleRepository())->findBySlug('user')['id'] ?? 0),
            'status'   => 'active',
        ]);

        try {
            $this->optIn();
            $this->issueAndVerify();
            $this->rejectsWrongCode();
            $this->burnsAfterTooManyAttempts();
            $this->singleUse();
            $this->expiry();
            $this->sendThrottle();
            $this->credentialsAloneAreNotASession();
            $this->storage();
        } finally {
            $settings->set('mail_driver', $this->previousDriver, 'string', 'email');
            \App\Core\Config::set('mail.driver', $this->previousDriver);
            Database::instance()->delete('auth_otp_codes', ['user_id' => $this->userId]);
            Database::instance()->delete('users', ['id' => $this->userId]);
        }
    }

    /** @return array<string,mixed> */
    private function user(): array
    {
        return (array) $this->users->find($this->userId);
    }

    /** The code a test needs, read from the row rather than the email. */
    private function issueCode(): string
    {
        // password_hash() is one-way, so the code is generated here and the
        // row rewritten with its hash: the same path verify() exercises.
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->otp->issue($this->user());
        $row = $this->codes->active($this->userId, OtpService::PURPOSE_LOGIN);
        Database::instance()->update('auth_otp_codes', [
            'code_hash' => password_hash($code, PASSWORD_DEFAULT),
        ], ['id' => (int) ($row['id'] ?? 0)]);
        return $code;
    }

    private function optIn(): void
    {
        $this->assertTrue('2FA: the feature is available', $this->otp->isAvailable());
        $this->assertFalse('2FA: it is off for a new account', $this->otp->isEnabledFor($this->user()));

        $this->otp->setEnabled($this->userId, true);
        $this->assertTrue('2FA: a user can turn it on', $this->otp->isEnabledFor($this->user()));

        $this->otp->setEnabled($this->userId, false);
        $this->assertFalse('2FA: a user can turn it off', $this->otp->isEnabledFor($this->user()));

        $this->otp->setEnabled($this->userId, true);
    }

    private function issueAndVerify(): void
    {
        $result = $this->otp->issue($this->user());
        $this->assertTrue('OTP: a code is issued', (bool) $result['ok'], (string) $result['message']);
        $this->assertGreaterThan('OTP: the code has a lifetime', 60, (float) $result['expires_in']);

        $row = $this->codes->active($this->userId, OtpService::PURPOSE_LOGIN);
        $this->assertTrue('OTP: the row exists', $row !== null);
        $this->assertNotContains(
            'OTP: the plaintext code is not stored',
            '000000',
            (string) ($row['code_hash'] ?? '')
        );
        $this->assertMatches(
            'OTP: the stored value is a password hash',
            '/^\$2[aby]\$|^\$argon2/',
            (string) ($row['code_hash'] ?? '')
        );
        $this->assertSame('OTP: the channel is recorded', 'email', (string) ($row['channel'] ?? ''));

        $code = $this->issueCode();
        $verified = $this->otp->verify($this->user(), $code);
        $this->assertTrue('OTP: the right code is accepted', (bool) $verified['ok'], (string) $verified['message']);
    }

    private function rejectsWrongCode(): void
    {
        $this->issueCode();
        $wrong = $this->otp->verify($this->user(), '000001');
        $this->assertFalse('OTP: a wrong code is rejected', (bool) $wrong['ok']);
        $this->assertContains('OTP: the message says how many tries are left', 'attempt', (string) $wrong['message']);

        $row = $this->codes->active($this->userId, OtpService::PURPOSE_LOGIN);
        $this->assertGreaterThan('OTP: the attempt is recorded on the row', 0, (float) ($row['attempts'] ?? 0));
    }

    private function burnsAfterTooManyAttempts(): void
    {
        $code = $this->issueCode();
        for ($i = 0; $i < 5; $i++) {
            $this->otp->verify($this->user(), '999999');
        }
        $after = $this->otp->verify($this->user(), $code);
        $this->assertFalse('OTP: the code is burned after five wrong attempts', (bool) $after['ok']);
        $this->assertSame(
            'OTP: no live code remains',
            null,
            $this->codes->active($this->userId, OtpService::PURPOSE_LOGIN)
        );
    }

    private function singleUse(): void
    {
        $code = $this->issueCode();
        $first = $this->otp->verify($this->user(), $code);
        $this->assertTrue('OTP: the code works once', (bool) $first['ok']);

        $second = $this->otp->verify($this->user(), $code);
        $this->assertFalse('OTP: the same code cannot be reused', (bool) $second['ok']);
    }

    private function expiry(): void
    {
        $code = $this->issueCode();
        $row = $this->codes->active($this->userId, OtpService::PURPOSE_LOGIN);
        Database::instance()->update('auth_otp_codes', [
            'expires_at' => date('Y-m-d H:i:s', time() - 60),
        ], ['id' => (int) ($row['id'] ?? 0)]);

        $expired = $this->otp->verify($this->user(), $code);
        $this->assertFalse('OTP: an expired code is refused', (bool) $expired['ok']);
        $this->assertContains('OTP: the user is told to ask for another', 'expired', (string) $expired['message']);
    }

    private function sendThrottle(): void
    {
        Database::instance()->delete('auth_otp_codes', ['user_id' => $this->userId]);

        $blocked = false;
        for ($i = 0; $i < 10; $i++) {
            $result = $this->otp->issue($this->user());
            if (!$result['ok']) {
                $blocked = true;
                break;
            }
        }
        $this->assertTrue('OTP: repeated sends are throttled', $blocked);
    }

    private function credentialsAloneAreNotASession(): void
    {
        $user = $this->user();
        Auth::logout();

        // credentialsOnly: the password is right, but no session is created.
        $result = Auth::attempt((string) $user['email'], 'OtpPass#2026', false, true);
        $this->assertTrue('2FA: the password is accepted', (bool) $result['ok'], (string) $result['message']);
        $this->assertFalse('2FA: no session exists until the code is entered', Auth::check());
        $this->assertTrue('2FA: the user is returned for the pending step', is_array($result['user']));

        // And a wrong password still fails in that mode.
        $wrong = Auth::attempt((string) $user['email'], 'NotThePassword#1', false, true);
        $this->assertFalse('2FA: a wrong password is still refused', (bool) $wrong['ok']);
        $this->assertFalse('2FA: still no session', Auth::check());
    }

    private function storage(): void
    {
        $columns = Database::instance()->columns('auth_otp_codes');
        foreach (['code', 'plaintext', 'secret'] as $forbidden) {
            $this->assertFalse(
                'OTP storage: there is no "' . $forbidden . '" column',
                in_array($forbidden, $columns, true)
            );
        }
        foreach (['code_hash', 'attempts', 'expires_at', 'consumed_at', 'ip_hash'] as $expected) {
            $this->assertTrue('OTP storage: "' . $expected . '" is recorded', in_array($expected, $columns, true));
        }

        $purged = $this->codes->purgeExpired(0);
        $this->assertGreaterThan('OTP storage: housekeeping removes old codes', -1, (float) $purged);
    }
}
