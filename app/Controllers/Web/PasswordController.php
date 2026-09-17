<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Lang;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Core\ValidationException;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\MailService;
use App\Services\SeoService;

/**
 * Password reset.
 *
 * A selector/validator pair is emailed; only the validator's hash is stored,
 * so the database alone cannot be used to reset anyone's password. The
 * response is identical whether or not the address exists, so the form cannot
 * be used to enumerate accounts.
 */
final class PasswordController extends Controller
{
    private const TOKEN_TTL = 3600; // one hour

    public function __construct(
        private readonly UserRepository $users = new UserRepository()
    ) {
    }

    public function showForgot(Request $request): Response
    {
        return $this->view('auth.forgot', [
            'seo' => SeoService::make()->title(Lang::get('auth.reset_title'))->noindex(),
        ]);
    }

    public function sendLink(Request $request): Response
    {
        try {
            $data = $this->validate($request, ['email' => 'required|email'], ['email' => Lang::get('auth.email')]);
        } catch (ValidationException $e) {
            return $this->validationResponse($e, $request);
        }

        $email = strtolower((string) $data['email']);
        $user = $this->users->findByEmail($email);

        if ($user !== null && (string) $user['status'] === 'active') {
            $selector = bin2hex(random_bytes(8));
            $validator = bin2hex(random_bytes(24));
            $db = $this->users->db();

            // One live token per address.
            $db->execute(
                'DELETE FROM ' . $db->wrap($db->table('password_resets')) . ' WHERE email = :email',
                ['email' => $email]
            );
            $db->insert('password_resets', [
                'email'      => $email,
                'selector'   => $selector,
                'token_hash' => hash('sha256', $validator),
                'expires_at' => time() + self::TOKEN_TTL,
                'ip_hash'    => Logger::clientIpHash(),
                'created_at' => Database::now(),
            ]);

            $result = (new MailService())->sendTemplate(
                $email,
                (string) $user['name'],
                'Reset your password',
                'reset',
                [
                    'resetUrl' => Url::to('password/reset/' . $selector . '.' . $validator),
                    'minutes'  => (int) (self::TOKEN_TTL / 60),
                ]
            );
            if (!$result['ok']) {
                Logger::warning('Password reset email could not be sent', ['ok' => false], Logger::MAIL);
            }
            AuditService::instance()->log('auth.reset_requested', 'user', (int) $user['id']);
        }

        // Identical response either way.
        $message = 'If that email address has an account, a reset link is on its way. '
            . 'Please also check your spam folder.';

        if ($request->expectsJson()) {
            return $this->success(null, $message);
        }
        $this->flash('success', $message);
        return $this->redirect('/login');
    }

    public function showReset(Request $request): Response
    {
        $token = (string) $request->param('token');
        $verification = $this->verifyToken($token);

        if (!$verification['ok']) {
            $this->flash('danger', $verification['message']);
            return $this->redirect('/password/forgot');
        }

        return $this->view('auth.reset', [
            'seo'   => SeoService::make()->title(Lang::get('auth.reset_title'))->noindex(),
            'token' => $token,
            'email' => $verification['email'],
        ]);
    }

    public function reset(Request $request): Response
    {
        try {
            $data = $this->validate($request, [
                'token'    => 'required|string|max:120',
                'password' => 'required|password|confirmed',
            ], ['password' => Lang::get('auth.password')]);
        } catch (ValidationException $e) {
            return $this->validationResponse($e, $request);
        }

        $verification = $this->verifyToken((string) $data['token']);
        if (!$verification['ok']) {
            $this->flash('danger', $verification['message']);
            return $this->redirect('/password/forgot');
        }

        $user = $this->users->findByEmail((string) $verification['email']);
        if ($user === null) {
            $this->flash('danger', 'That account no longer exists.');
            return $this->redirect('/login');
        }

        $this->users->update((int) $user['id'], [
            'password' => Auth::hash((string) $request->raw('password', '')),
        ]);
        // Any "remember me" cookie issued before the reset must stop working.
        $this->users->clearRememberToken((int) $user['id']);

        $db = $this->users->db();
        $db->execute(
            'UPDATE ' . $db->wrap($db->table('password_resets'))
            . ' SET used_at = :now WHERE selector = :selector',
            ['now' => Database::now(), 'selector' => (string) $verification['selector']]
        );

        AuditService::instance()->log('auth.reset_completed', 'user', (int) $user['id']);
        Logger::info('Password reset completed', ['user_id' => $user['id']], Logger::LOGIN);

        $this->flash('success', 'Your password has been changed. Please sign in.');
        return $this->redirect('/login');
    }

    /** @return array{ok:bool,message:string,email:string|null,selector:string|null} */
    private function verifyToken(string $token): array
    {
        $invalid = [
            'ok'       => false,
            'message'  => 'That reset link is invalid or has expired. Please request a new one.',
            'email'    => null,
            'selector' => null,
        ];

        if (!str_contains($token, '.')) {
            return $invalid;
        }
        [$selector, $validator] = explode('.', $token, 2);
        if (strlen($selector) !== 16 || strlen($validator) !== 48) {
            return $invalid;
        }

        $db = $this->users->db();
        $row = $db->first(
            'SELECT * FROM ' . $db->wrap($db->table('password_resets')) . ' WHERE selector = :selector LIMIT 1',
            ['selector' => $selector]
        );
        if ($row === null || $row['used_at'] !== null) {
            return $invalid;
        }
        if ((int) $row['expires_at'] < time()) {
            return $invalid;
        }
        if (!hash_equals((string) $row['token_hash'], hash('sha256', $validator))) {
            Logger::security('Invalid password reset validator presented', ['selector' => $selector]);
            return $invalid;
        }

        return [
            'ok'       => true,
            'message'  => 'Token valid.',
            'email'    => (string) $row['email'],
            'selector' => $selector,
        ];
    }
}
