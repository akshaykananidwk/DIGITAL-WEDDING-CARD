<?php

declare(strict_types=1);

namespace Tests\Cases;

use App\Core\Auth;
use App\Core\Crypto;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Str;
use App\Services\ArchiveExtractor;
use App\Services\TemplateEngine;
use Tests\TestCase;

/** Section 60: SQL injection, XSS, CSRF, uploads, auth bypass, IDOR, traversal, Zip Slip. */
final class SecurityTest extends TestCase
{
    public function name(): string
    {
        return 'Security';
    }

    public function run(): void
    {
        $this->sqlInjection();
        $this->crossSiteScripting();
        $this->csrf();
        $this->passwordHashing();
        $this->encryption();
        $this->identifierWhitelisting();
        $this->pathTraversal();
        $this->zipSlip();
        $this->secretMasking();
    }

    private function sqlInjection(): void
    {
        $db = Database::instance();

        // A classic payload passed as a bound value must be treated as data.
        $payload = "' OR 1=1 -- ";
        $count = (int) $db->value(
            'SELECT COUNT(*) FROM ' . $db->wrap($db->table('users')) . ' WHERE email = :email',
            ['email' => $payload],
            0
        );
        $this->assertSame('SQLi: bound value matches nothing', 0, $count);

        // And the table is still there afterwards.
        $payload2 = "x'; DROP TABLE " . $db->table('users') . '; --';
        $db->value(
            'SELECT COUNT(*) FROM ' . $db->wrap($db->table('users')) . ' WHERE name = :name',
            ['name' => $payload2],
            0
        );
        $this->assertTrue('SQLi: users table survives a DROP payload', $db->tableExists('users'));

        // The repository search path (LIKE + FULLTEXT) survives it too.
        $templates = new \App\Repositories\TemplateRepository();
        $result = $templates->search(['q' => "' UNION SELECT password FROM users -- ", 'active' => true], 1, 5);
        $this->assertTrue('SQLi: template search returns a normal result set', is_array($result['rows']));
        $this->assertTrue('SQLi: search leaks no password column', !isset($result['rows'][0]['password']));
    }

    private function crossSiteScripting(): void
    {
        $payload = '<script>alert(1)</script>';
        $this->assertSame(
            'XSS: e() escapes a script tag',
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            e($payload)
        );
        $this->assertNotContains('XSS: eattr() escapes quotes', '"', eattr('a"b'));
        $this->assertNotContains('XSS: ejs() escapes a closing script tag', '</script>', ejs(['x' => '</script>']));

        // Admin-authored template HTML is sanitised, not trusted.
        $engine = new TemplateEngine();
        $dirty = '<p onclick="steal()">Hello</p><script>alert(1)</script><iframe src="//evil"></iframe>'
            . '<a href="javascript:alert(1)">x</a>';
        $clean = $engine->sanitiseHtml($dirty);
        $this->assertNotContains('XSS: sanitiser drops <script>', '<script', $clean);
        $this->assertNotContains('XSS: sanitiser drops <iframe>', '<iframe', $clean);
        $this->assertNotContains('XSS: sanitiser drops event attributes', 'onclick', $clean);
        $this->assertNotContains('XSS: sanitiser drops javascript: URLs', 'javascript:', $clean);
        $this->assertContains('XSS: sanitiser keeps the text', 'Hello', $clean);

        $css = 'body { color: red; } @import url("//evil/x.css"); a { background: url(javascript:alert(1)); }';
        $cleanCss = $engine->sanitiseCss($css);
        $this->assertNotContains('XSS: CSS sanitiser drops @import', '@import', $cleanCss);
        $this->assertNotContains('XSS: CSS sanitiser drops javascript: URLs', 'javascript:', $cleanCss);
    }

    private function csrf(): void
    {
        $token = Csrf::token();
        $this->assertGreaterThan('CSRF: token is long enough', 30, (float) strlen($token));
        $this->assertTrue('CSRF: the issued token validates', Csrf::check($token));
        $this->assertFalse('CSRF: a forged token is rejected', Csrf::check('not-the-token'));
        $this->assertFalse('CSRF: an empty token is rejected', Csrf::check(''));
        $this->assertContains('CSRF: csrf_field() emits a hidden input', 'type="hidden"', csrf_field());
    }

    private function passwordHashing(): void
    {
        $hash = Auth::hash('Kankotri#2026');
        $this->assertNotContains('Auth: the hash does not contain the password', 'Kankotri#2026', $hash);
        $this->assertTrue('Auth: password_verify accepts the password', password_verify('Kankotri#2026', $hash));
        $this->assertFalse('Auth: password_verify rejects a wrong password', password_verify('wrong', $hash));
        $this->assertSame(
            'Auth: two hashes of one password differ (salted)',
            false,
            $hash === Auth::hash('Kankotri#2026')
        );
    }

    private function encryption(): void
    {
        $secret = 'ghp_averyrealisticlookingtokenvalue1234567890';
        $cipher = Crypto::encrypt($secret);
        $this->assertNotContains('Crypto: ciphertext does not contain the plaintext', $secret, $cipher);
        $this->assertSame('Crypto: round trip returns the plaintext', $secret, Crypto::decrypt($cipher));
        $this->assertSame('Crypto: a tampered ciphertext decrypts to nothing', '', Crypto::decrypt($cipher . 'x'));
        $this->assertSame('Crypto: an empty value stays empty', '', Crypto::decrypt(''));
    }

    private function identifierWhitelisting(): void
    {
        $db = Database::instance();
        $this->assertNotContains('Database: wrap() strips a backtick payload', '`;', $db->wrap('users`; DROP TABLE x; --'));
        $this->assertNotContains('Database: wrap() strips a space payload', ' ', $db->wrap('users OR 1=1'));
    }

    private function pathTraversal(): void
    {
        foreach ([
            '../../etc/passwd',
            '..\\..\\windows\\system32',
            'uploads/../../.invitation-secrets/app.php',
            '/etc/passwd',
        ] as $candidate) {
            $safe = ArchiveExtractor::safeRelativePath($candidate);
            $this->assertTrue(
                'Traversal: rejected "' . $candidate . '"',
                $safe === null || (!str_contains($safe, '..') && !str_starts_with($safe, '/')),
                var_export($safe, true)
            );
        }

        $this->assertSame(
            'Traversal: an ordinary path is accepted unchanged',
            'app/Core/Router.php',
            ArchiveExtractor::safeRelativePath('app/Core/Router.php')
        );

        // The log viewer only ever reads from the log directory.
        $this->assertSame(
            'Traversal: Logger::tail() refuses a path outside the log directory',
            '',
            \App\Core\Logger::tail('../../../../etc/passwd', 5)
        );
    }

    private function zipSlip(): void
    {
        $zipPath = STORAGE_PATH . '/tmp/zipslip-test-' . bin2hex(random_bytes(4)) . '.zip';
        $target = STORAGE_PATH . '/tmp/zipslip-out-' . bin2hex(random_bytes(4));

        if (!class_exists(\ZipArchive::class)) {
            $this->pass('Zip Slip: skipped, the zip extension is not installed');
            return;
        }

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('harmless.txt', 'ok');
        $zip->addFromString('../../escaped.txt', 'should never be written');
        $zip->addFromString('nested/../../escaped2.txt', 'should never be written');
        $zip->close();

        // No first component to strip here: the entries sit at the archive root,
        // unlike a GitHub tarball.
        $result = ArchiveExtractor::extractZip($zipPath, $target, false);

        $this->assertTrue('Zip Slip: extraction still succeeds for safe entries', (bool) $result['ok'], (string) $result['message']);
        $this->assertTrue('Zip Slip: the safe entry was written', is_file($target . '/harmless.txt'));
        $this->assertFalse('Zip Slip: ../escaped.txt was not written', is_file(dirname($target) . '/escaped.txt'));
        $this->assertFalse('Zip Slip: ../escaped2.txt was not written', is_file(dirname($target) . '/escaped2.txt'));
        $this->assertFalse(
            'Zip Slip: nothing escaped two levels up',
            is_file(dirname(dirname($target)) . '/escaped.txt')
        );

        ArchiveExtractor::deleteDirectory($target);
        @unlink($zipPath);
    }

    private function secretMasking(): void
    {
        $masked = Str::maskSecret('ghp_1234567890abcdefghijklmnopqrstuvwxyz');
        $this->assertNotContains('Secrets: the masked token hides the middle', 'abcdefghijklmnop', $masked);
        $this->assertContains('Secrets: the masked token keeps a hint', '•', $masked);
        $this->assertContains('Secrets: the masked token keeps the prefix', 'ghp_', $masked);
        $this->assertSame('Secrets: masking an empty value is empty', '', Str::maskSecret(''));
    }
}
