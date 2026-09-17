<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Str;
use App\Core\Url;
use App\Core\ValidationException;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\FeatureFlagService;
use App\Services\MailService;
use App\Services\SeoService;
use App\Services\SettingsService;

final class AuthController extends Controller
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository()
    ) {
    }

    public function showLogin(Request $request): Response
    {
        return $this->view('auth.login', [
            'seo' => SeoService::make()->title(Lang::get('auth.login_title'))->noindex(),
        ]);
    }

    public function login(Request $request): Response
    {
        try {
            $data = $this->validate($request, [
                'email'    => 'required|email',
                'password' => 'required|string|max:200',
            ], [
                'email'    => Lang::get('auth.email'),
                'password' => Lang::get('auth.password'),
            ]);
        } catch (ValidationException $e) {
            return $this->validationResponse($e, $request);
        }

        $result = Auth::attempt(
            (string) $data['email'],
            (string) $request->raw('password', ''),
            $request->bool('remember')
        );

        if (!$result['ok']) {
            if ($request->expectsJson()) {
                return $this->error($result['message'], 422);
            }
            $this->flash('danger', $result['message']);
            return $this->back(['email' => [$result['message']]], ['email' => $data['email']]);
        }

        $intended = Session::pull('_intended');
        $target = is_string($intended) && $intended !== ''
            ? $intended
            : (Auth::isAdmin() ? '/admin' : '/dashboard');

        if ($request->expectsJson()) {
            return $this->success(['redirect' => Url::to($target)], $result['message']);
        }

        $this->flash('success', $result['message']);
        return $this->redirect($target);
    }

    public function showRegister(Request $request): Response
    {
        if (!$this->registrationOpen()) {
            $this->flash('info', 'New registrations are closed at the moment.');
            return $this->redirect('/login');
        }

        return $this->view('auth.register', [
            'seo' => SeoService::make()->title(Lang::get('auth.register_title'))->noindex(),
        ]);
    }

    public function register(Request $request): Response
    {
        if (!$this->registrationOpen()) {
            return $request->expectsJson()
                ? $this->error('Registrations are closed.', 403)
                : $this->redirect('/login');
        }

        try {
            $data = $this->validate($request, [
                'name'     => 'required|string|min:2|max:120|no_html',
                'email'    => 'required|email|unique:users,email',
                'phone'    => 'nullable|phone',
                'password' => 'required|password|confirmed',
                'terms'    => 'required',
            ], [
                'name'     => Lang::get('auth.name'),
                'email'    => Lang::get('auth.email'),
                'phone'    => Lang::get('auth.phone'),
                'password' => Lang::get('auth.password'),
                'terms'    => 'Terms acceptance',
            ]);
        } catch (ValidationException $e) {
            return $this->validationResponse($e, $request);
        }

        $role = (new RoleRepository())->findBySlug('user');
        if ($role === null) {
            $this->flash('danger', 'The account could not be created. Please contact support.');
            return $this->back();
        }

        $requiresVerification = SettingsService::instance()->bool('require_email_verification', false);
        $planId = $this->users->db()->value(
            'SELECT id FROM ' . $this->users->db()->wrap($this->users->db()->table('plans'))
            . ' WHERE is_default = 1 LIMIT 1'
        );

        $userId = $this->users->create([
            'name'     => (string) $data['name'],
            'email'    => strtolower((string) $data['email']),
            'phone'    => isset($data['phone']) ? Str::phone((string) $data['phone']) : null,
            'password' => Auth::hash((string) $request->raw('password', '')),
            'role_id'  => (int) $role['id'],
            'plan_id'  => $planId === null ? null : (int) $planId,
            'status'   => $requiresVerification ? 'pending' : 'active',
            'locale'   => Lang::locale(),
            'email_verified_at' => $requiresVerification ? null : \App\Core\Database::now(),
        ]);

        AuditService::instance()->log('auth.register', 'user', $userId, 'New account created');

        if ($requiresVerification) {
            $token = bin2hex(random_bytes(24));
            $this->users->setVerifyToken($userId, $token);
            (new MailService())->sendTemplate(
                (string) $data['email'],
                (string) $data['name'],
                'Confirm your email address',
                'verify',
                ['verifyUrl' => Url::to('verify/' . $token)]
            );
            $this->flash('success', 'Almost there! Please check your email for a confirmation link.');
            return $this->redirect('/login');
        }

        $user = $this->users->find($userId);
        if ($user !== null) {
            Auth::login($user);
        }

        if ($request->expectsJson()) {
            return $this->success(['redirect' => Url::to('/create')], 'Welcome!');
        }

        $this->flash('success', 'Welcome! Let us create your first invitation.');
        return $this->redirect('/create');
    }

    public function verifyEmail(Request $request): Response
    {
        $token = (string) $request->param('token');
        $user = $this->users->findByVerifyToken($token);
        if ($user === null) {
            $this->flash('danger', 'That confirmation link is invalid or has already been used.');
            return $this->redirect('/login');
        }

        $this->users->markEmailVerified((int) $user['id']);
        AuditService::instance()->log('auth.verified', 'user', (int) $user['id']);

        $fresh = $this->users->find((int) $user['id']);
        if ($fresh !== null) {
            Auth::login($fresh);
        }

        $this->flash('success', 'Thank you - your email address is confirmed.');
        return $this->redirect('/create');
    }

    public function logout(Request $request): Response
    {
        Auth::logout();
        Session::flash('info', Lang::get('auth.logged_out'));

        if ($request->expectsJson()) {
            return $this->success(['redirect' => Url::to('/')], Lang::get('auth.logged_out'));
        }
        return $this->redirect('/');
    }

    private function registrationOpen(): bool
    {
        return SettingsService::instance()->bool('registration_open', true)
            && FeatureFlagService::instance()->enabled('registration', true);
    }
}
