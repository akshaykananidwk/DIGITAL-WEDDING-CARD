<?php

declare(strict_types=1);

namespace Tests\Cases;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Validator;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use Tests\TestCase;

/** Section 60: registration, login, roles, permissions, throttling, ownership. */
final class AuthTest extends TestCase
{
    private UserRepository $users;
    private int $userId = 0;
    private string $email = '';

    public function name(): string
    {
        return 'Authentication and authorisation';
    }

    public function run(): void
    {
        $this->users = new UserRepository();
        $this->email = 'suite-' . bin2hex(random_bytes(4)) . '@example.test';

        try {
            $this->registration();
            $this->login();
            $this->throttling();
            $this->roles();
            $this->ownership();
        } finally {
            $this->cleanUp();
        }
    }

    private function registration(): void
    {
        $roles = new RoleRepository();
        $role = $roles->findBySlug('user');
        $this->assertTrue('Roles: the default user role exists', $role !== null);

        $this->userId = $this->users->create([
            'name'     => 'Suite User',
            'email'    => $this->email,
            'password' => Auth::hash('SuitePass#2026'),
            'role_id'  => (int) ($role['id'] ?? 0),
            'status'   => 'active',
            'locale'   => 'gu',
        ]);
        $this->assertGreaterThan('Registration: a user row is created', 0, (float) $this->userId);

        $stored = $this->users->find($this->userId);
        $this->assertNotContains(
            'Registration: the password is stored hashed',
            'SuitePass#2026',
            (string) ($stored['password'] ?? '')
        );

        // Validation rules that guard the public form.
        $validator = Validator::make(
            ['email' => 'not-an-email', 'password' => 'short'],
            ['email' => 'required|email', 'password' => 'required|password']
        );
        $this->assertTrue('Registration: an invalid email is rejected', $validator->fails());
        $this->assertTrue('Registration: a weak password is rejected', isset($validator->errors()['password']));

        $unique = Validator::make(
            ['email' => $this->email],
            ['email' => 'required|email|unique:users,email']
        );
        $this->assertTrue('Registration: a duplicate email is rejected', $unique->fails());
    }

    private function login(): void
    {
        $result = Auth::attempt($this->email, 'SuitePass#2026');
        $this->assertTrue('Login: correct credentials are accepted', (bool) $result['ok'], (string) $result['message']);
        $this->assertTrue('Login: the session now has a user', Auth::check());
        $this->assertSame('Login: the signed-in id matches', $this->userId, Auth::id());

        Auth::logout();
        $this->assertFalse('Logout: the session is cleared', Auth::check());

        $wrong = Auth::attempt($this->email, 'WrongPass#2026');
        $this->assertFalse('Login: a wrong password is rejected', (bool) $wrong['ok']);
        // The message must stay ambiguous about which half was wrong.
        $this->assertContains(
            'Login: the failure message names neither field alone',
            'email address or password',
            strtolower((string) $wrong['message'])
        );

        $missing = Auth::attempt('nobody-' . bin2hex(random_bytes(3)) . '@example.test', 'whatever');
        $this->assertFalse('Login: an unknown account is rejected', (bool) $missing['ok']);
        $this->assertSame(
            'Login: unknown and wrong-password give the same message (no user enumeration)',
            strtolower((string) $wrong['message']),
            strtolower((string) $missing['message'])
        );
    }

    private function throttling(): void
    {
        $email = 'throttle-' . bin2hex(random_bytes(4)) . '@example.test';
        $blocked = false;
        for ($attempt = 1; $attempt <= 40; $attempt++) {
            $result = Auth::attempt($email, 'nope');
            if (str_contains(strtolower((string) $result['message']), 'too many')) {
                $blocked = true;
                break;
            }
        }
        $this->assertTrue('Throttling: repeated failures are locked out', $blocked);
    }

    private function roles(): void
    {
        $roles = new RoleRepository();
        $superAdmin = $roles->findBySlug('super-admin');
        $user = $roles->findBySlug('user');

        $this->assertTrue('RBAC: the super admin role exists', $superAdmin !== null);
        $this->assertGreaterThan(
            'RBAC: the super admin outranks a user',
            (float) ($user['level'] ?? 0),
            (float) ($superAdmin['level'] ?? 0)
        );

        $userPermissions = $roles->permissionSlugs((int) ($user['id'] ?? 0));
        $this->assertFalse(
            'RBAC: a plain user cannot manage users',
            in_array('users.delete', $userPermissions, true)
        );
        $this->assertFalse(
            'RBAC: a plain user cannot apply updates',
            in_array('updates.apply', $userPermissions, true)
        );

        // A signed-in plain user is refused the admin panel.
        Auth::login((array) $this->users->find($this->userId));
        $this->assertFalse('RBAC: a plain user is not an admin', Auth::isAdmin());
        $this->assertFalse('RBAC: a plain user is not a super admin', Auth::isSuperAdmin());
        $this->assertFalse('RBAC: a plain user cannot edit settings', Auth::can('settings.edit'));
        Auth::logout();
    }

    private function ownership(): void
    {
        $db = Database::instance();
        $invitationId = (int) $db->value(
            'SELECT id FROM ' . $db->wrap($db->table('invitations')) . ' WHERE deleted_at IS NULL LIMIT 1'
        );
        if ($invitationId === 0) {
            $this->pass('IDOR: skipped, no invitation to test with');
            return;
        }

        $invitations = new \App\Repositories\InvitationRepository();
        $ownerId = (int) $db->value(
            'SELECT user_id FROM ' . $db->wrap($db->table('invitations')) . ' WHERE id = :id',
            ['id' => $invitationId]
        );

        $this->assertTrue(
            'IDOR: the owner can load their invitation',
            $invitations->findOwned($invitationId, $ownerId) !== null
        );
        $this->assertTrue(
            'IDOR: another user cannot load it',
            $invitations->findOwned($invitationId, $this->userId) === null
        );

        // The service turns that into a 404, never a 403, so an id cannot be
        // confirmed as existing by probing.
        Auth::login((array) $this->users->find($this->userId));
        $service = new \App\Services\InvitationService();
        $this->assertThrows(
            'IDOR: findOwnedOrFail() raises a not-found for someone else\'s invitation',
            static fn () => $service->findOwnedOrFail($invitationId),
            'not'
        );
        Auth::logout();
    }

    private function cleanUp(): void
    {
        if ($this->userId > 0) {
            Database::instance()->delete('users', ['id' => $this->userId]);
        }
    }
}
