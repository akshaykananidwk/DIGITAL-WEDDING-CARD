<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Core\ValidationException;
use App\Repositories\ApiTokenRepository;
use App\Repositories\InvitationRepository;
use App\Repositories\MediaRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\InvitationService;
use App\Services\MediaService;
use App\Services\SeoService;

final class ProfileController extends Controller
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository()
    ) {
    }

    public function edit(Request $request): Response
    {
        $user = Auth::user();

        return $this->view('dashboard.profile', [
            'seo'      => SeoService::make()->title(Lang::get('nav.profile'))->noindex(),
            'user'     => $user,
            'storage'  => (new MediaRepository())->storageStats((int) Auth::id()),
            'limits'   => (new InvitationService())->planLimits((array) $user),
            'tokens'   => (new ApiTokenRepository())->forUser((int) Auth::id()),
            'locales'  => Lang::available(),
        ]);
    }

    public function update(Request $request): Response
    {
        $userId = (int) Auth::id();

        try {
            $data = $this->validate($request, [
                'name'   => 'required|string|min:2|max:120|no_html',
                'email'  => 'required|email|unique:users,email,' . $userId,
                'phone'  => 'nullable|phone',
                'city'   => 'nullable|string|max:80|no_html',
                'locale' => 'nullable|locale',
            ], [
                'name'  => Lang::get('auth.name'),
                'email' => Lang::get('auth.email'),
                'phone' => Lang::get('auth.phone'),
            ]);
        } catch (ValidationException $e) {
            return $this->validationResponse($e, $request);
        }

        $before = Auth::user() ?? [];

        $update = [
            'name'  => (string) $data['name'],
            'email' => strtolower((string) $data['email']),
            'phone' => isset($data['phone']) ? Str::phone((string) $data['phone']) : null,
            'city'  => $data['city'] ?? null,
        ];
        if (isset($data['locale'])) {
            $update['locale'] = (string) $data['locale'];
            Lang::setLocale((string) $data['locale']);
        }

        // Changing the email address invalidates verification.
        if ($update['email'] !== strtolower((string) ($before['email'] ?? ''))) {
            $update['email_verified_at'] = null;
        }

        $this->users->update($userId, $update);
        Auth::refresh();

        AuditService::instance()->logChanges('profile.update', 'user', $userId, $before, $update);

        if ($request->expectsJson()) {
            return $this->success(null, 'Profile updated.');
        }
        $this->flash('success', 'Your profile has been updated.');
        return $this->redirect('profile');
    }

    public function changePassword(Request $request): Response
    {
        $userId = (int) Auth::id();
        $user = Auth::user();

        try {
            $this->validate($request, [
                'current_password' => 'required|string',
                'password'         => 'required|password|confirmed',
            ], [
                'current_password' => 'Current password',
                'password'         => 'New password',
            ]);
        } catch (ValidationException $e) {
            return $this->validationResponse($e, $request);
        }

        if (!is_array($user) || !password_verify((string) $request->raw('current_password', ''), (string) $user['password'])) {
            $message = 'Your current password is not correct.';
            return $request->expectsJson()
                ? $this->error($message, 422, ['current_password' => [$message]])
                : $this->back(['current_password' => [$message]]);
        }

        $this->users->update($userId, ['password' => Auth::hash((string) $request->raw('password', ''))]);
        $this->users->clearRememberToken($userId);
        AuditService::instance()->log('profile.password', 'user', $userId, 'Password changed');

        if ($request->expectsJson()) {
            return $this->success(null, 'Password changed.');
        }
        $this->flash('success', 'Your password has been changed.');
        return $this->redirect('profile');
    }

    public function uploadAvatar(Request $request): Response
    {
        $file = $request->file('avatar');
        if ($file === null) {
            return $this->back(['avatar' => ['Please choose an image.']]);
        }

        try {
            $stored = (new MediaService())->storeImage($file, 'avatars', false);
        } catch (\Throwable $e) {
            return $request->expectsJson()
                ? $this->error($e->getMessage(), 422)
                : $this->back(['avatar' => [$e->getMessage()]]);
        }

        $user = Auth::user();
        $previous = (string) ($user['avatar'] ?? '');
        if ($previous !== '') {
            (new MediaService())->deleteFiles([$previous]);
        }

        $this->users->update((int) Auth::id(), ['avatar' => $stored['path']]);
        Auth::refresh();

        if ($request->expectsJson()) {
            return $this->success(['url' => \App\Core\Url::upload($stored['path'])], 'Photo updated.');
        }
        $this->flash('success', 'Your photo has been updated.');
        return $this->redirect('profile');
    }

    /** GDPR-style data export of everything this user owns. */
    public function exportData(Request $request): Response
    {
        $userId = (int) Auth::id();
        $user = Auth::user() ?? [];
        $invitations = new InvitationRepository();
        $dataRepository = new \App\Repositories\InvitationDataRepository();
        $rsvpRepository = new \App\Repositories\RsvpRepository();

        $payload = [
            'exported_at' => gmdate('c'),
            'account' => [
                'name'       => $user['name'] ?? '',
                'email'      => $user['email'] ?? '',
                'phone'      => $user['phone'] ?? '',
                'city'       => $user['city'] ?? '',
                'locale'     => $user['locale'] ?? 'en',
                'created_at' => $user['created_at'] ?? '',
            ],
            'invitations' => [],
        ];

        foreach ($invitations->forUser($userId) as $invitation) {
            $payload['invitations'][] = [
                'title'      => $invitation['title'],
                'slug'       => $invitation['slug'],
                'status'     => $invitation['status'],
                'created_at' => $invitation['created_at'],
                'content'    => $dataRepository->forInvitation((int) $invitation['id']),
                'rsvp'       => $rsvpRepository->allForInvitation((int) $invitation['id']),
                'stats'      => [
                    'views'     => (int) $invitation['view_count'],
                    'shares'    => (int) $invitation['share_count'],
                    'downloads' => (int) $invitation['download_count'],
                ],
            ];
        }

        AuditService::instance()->log('profile.export', 'user', $userId, 'Data export downloaded');

        return Response::download(
            (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'my-data-' . date('Y-m-d') . '.json',
            'application/json'
        );
    }

    /** Delete the account and everything in it. */
    public function destroy(Request $request): Response
    {
        $userId = (int) Auth::id();
        $user = Auth::user();

        if (!is_array($user) || !password_verify((string) $request->raw('password', ''), (string) $user['password'])) {
            $message = 'Please enter your password to confirm.';
            return $this->back(['password' => [$message]]);
        }
        if ((string) $request->input('confirm') !== 'DELETE') {
            return $this->back(['confirm' => ['Type DELETE to confirm.']]);
        }

        // Purge content first so uploaded files go with it.
        $service = new InvitationService();
        foreach ((new InvitationRepository())->forUser($userId) as $invitation) {
            $service->purge($invitation);
        }

        $avatar = (string) ($user['avatar'] ?? '');
        if ($avatar !== '') {
            (new MediaService())->deleteFiles([$avatar]);
        }

        AuditService::instance()->log('profile.delete', 'user', $userId, 'Account deleted by the user');

        // The row is soft deleted so the audit trail keeps a reference, and
        // the email address is released for re-registration.
        $this->users->update($userId, [
            'email'  => 'deleted-' . $userId . '-' . bin2hex(random_bytes(4)) . '@deleted.invalid',
            'status' => 'suspended',
            'phone'  => null,
            'avatar' => null,
        ]);
        $this->users->delete($userId);

        Auth::logout();

        $this->flash('info', 'Your account and invitations have been deleted. We are sorry to see you go.');
        return $this->redirect('/');
    }
}
