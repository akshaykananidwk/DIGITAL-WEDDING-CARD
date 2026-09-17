<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Repositories\OtpRepository;

/**
 * Second-factor login codes, delivered by email.
 *
 * Email rather than SMS because an SMS gateway means an account, a contract and
 * credentials this application should not assume. `deliver()` is the single
 * place a gateway would slot in, and the rest of the flow - issuing, hashing,
 * throttling, expiry, attempt limits, single use - is channel-agnostic.
 */
final class OtpService
{
    public const PURPOSE_LOGIN = 'login';

    /** How long a code is valid. */
    private const TTL_SECONDS = 600;
    /** Wrong guesses allowed before the code is burned. */
    private const MAX_ATTEMPTS = 5;
    /** Codes a user may be sent in an hour. */
    private const MAX_PER_HOUR = 6;

    public function __construct(
        private readonly OtpRepository $codes = new OtpRepository(),
        private readonly MailService $mail = new MailService()
    ) {
    }

    /** Is the second factor available at all on this installation? */
    public function isAvailable(): bool
    {
        return FeatureFlagService::instance()->enabled('two_factor_email', true);
    }

    /** Does this user have it switched on? */
    public function isEnabledFor(array $user): bool
    {
        return $this->isAvailable() && (int) ($user['two_factor_enabled'] ?? 0) === 1;
    }

    /**
     * Issue a code and send it.
     *
     * @return array{ok:bool,message:string,expires_in:int}
     */
    public function issue(array $user, string $purpose = self::PURPOSE_LOGIN): array
    {
        $userId = (int) $user['id'];

        if ($this->codes->issuedSince($userId, $purpose, 3600) >= self::MAX_PER_HOUR) {
            Logger::security('OTP send throttled', ['user_id' => $userId]);
            return [
                'ok'      => false,
                'message' => 'Too many codes have been requested. Please try again later.',
                'expires_in' => 0,
            ];
        }

        // Only one code is live at a time, so an older email cannot be used.
        $this->codes->invalidate($userId, $purpose);

        $code = $this->generateCode();
        $this->codes->create([
            'user_id'    => $userId,
            'purpose'    => $purpose,
            'channel'    => 'email',
            'code_hash'  => password_hash($code, PASSWORD_DEFAULT),
            'sent_to'    => (string) $user['email'],
            'ip_hash'    => Logger::clientIpHash(),
            'expires_at' => date('Y-m-d H:i:s', time() + self::TTL_SECONDS),
        ]);

        $delivery = $this->deliver($user, $code);
        if (!$delivery['ok']) {
            return [
                'ok'      => false,
                'message' => 'The code could not be sent. Please try again, or contact support.',
                'expires_in' => 0,
            ];
        }

        Logger::info('OTP issued', ['user_id' => $userId, 'purpose' => $purpose], Logger::LOGIN);

        return [
            'ok'      => true,
            'message' => 'We have emailed you a six-digit code.',
            'expires_in' => self::TTL_SECONDS,
        ];
    }

    /**
     * Check a code the user typed.
     *
     * @return array{ok:bool,message:string}
     */
    public function verify(array $user, string $candidate, string $purpose = self::PURPOSE_LOGIN): array
    {
        $candidate = preg_replace('/\D/', '', $candidate) ?? '';
        $row = $this->codes->active((int) $user['id'], $purpose);

        if ($row === null) {
            return ['ok' => false, 'message' => 'That code has expired. Please request a new one.'];
        }

        // Always run the hash comparison, so a wrong code and an expired one
        // take the same time.
        $matches = password_verify($candidate, (string) $row['code_hash']);

        if (!$matches) {
            $attempts = $this->codes->recordAttempt((int) $row['id']);
            if ($attempts >= self::MAX_ATTEMPTS) {
                $this->codes->consume((int) $row['id']);
                Logger::security('OTP burned after too many attempts', ['user_id' => $user['id']]);
                return [
                    'ok'      => false,
                    'message' => 'Too many incorrect attempts. Please request a new code.',
                ];
            }
            return [
                'ok'      => false,
                'message' => 'That code is not correct. ' . (self::MAX_ATTEMPTS - $attempts) . ' attempt(s) left.',
            ];
        }

        $this->codes->consume((int) $row['id']);
        Logger::info('OTP accepted', ['user_id' => $user['id'], 'purpose' => $purpose], Logger::LOGIN);

        return ['ok' => true, 'message' => 'Code accepted.'];
    }

    /** Turn the second factor on or off for a user. */
    public function setEnabled(int $userId, bool $enabled): void
    {
        Database::instance()->update(
            'users',
            ['two_factor_enabled' => $enabled ? 1 : 0, 'updated_at' => Database::now()],
            ['id' => $userId]
        );
        AuditService::instance()->log(
            $enabled ? 'auth.2fa_enabled' : 'auth.2fa_disabled',
            'user',
            $userId
        );
    }

    /**
     * Send the code.
     *
     * The one place a channel other than email would be added: an SMS gateway
     * would send here when the user has a verified phone number, and the rest
     * of this class would not change.
     *
     * @return array{ok:bool,message:string}
     */
    private function deliver(array $user, string $code): array
    {
        return $this->mail->sendTemplate(
            (string) $user['email'],
            (string) $user['name'],
            'Your sign-in code',
            'otp',
            [
                'code'    => $code,
                'minutes' => (int) (self::TTL_SECONDS / 60),
                'ip'      => '',
            ]
        );
    }

    /** A six-digit code from a cryptographic source, leading zeros kept. */
    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
