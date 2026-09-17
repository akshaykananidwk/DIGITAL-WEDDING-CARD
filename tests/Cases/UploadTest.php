<?php

declare(strict_types=1);

namespace Tests\Cases;

use App\Core\Database;
use App\Repositories\MediaRepository;
use App\Services\MediaService;
use Tests\TestCase;

/** Section 60: upload validation, re-encoding, naming and in-use protection. */
final class UploadTest extends TestCase
{
    private MediaService $media;
    /** @var array<int,string> */
    private array $written = [];

    public function name(): string
    {
        return 'Uploads and the media library';
    }

    public function run(): void
    {
        $this->media = new MediaService();

        try {
            $this->acceptsRealImages();
            $this->rejectsDisguisedFiles();
            $this->rejectsOversizedFiles();
            $this->safeNaming();
            $this->uploadDirectoryCannotExecutePhp();
            $this->inUseProtection();
        } finally {
            $this->cleanUp();
        }
    }

    /** Build a $_FILES-shaped array around a temporary file. */
    private function upload(string $name, string $bytes): array
    {
        $tmp = STORAGE_PATH . '/tmp/upload-' . bin2hex(random_bytes(5));
        file_put_contents($tmp, $bytes);
        $this->written[] = $tmp;

        return [
            'name'     => $name,
            'type'     => 'application/octet-stream',
            'tmp_name' => $tmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => strlen($bytes),
        ];
    }

    private function pngBytes(int $width = 64, int $height = 64): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 16, 46));
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);
        return $bytes;
    }

    private function acceptsRealImages(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->pass('Uploads: skipped, GD is not installed');
            return;
        }

        $stored = $this->media->storeImage($this->upload('holiday photo.png', $this->pngBytes(400, 300)), 'suite');
        $this->written[] = UPLOAD_PATH . '/' . $stored['path'];

        $this->assertTrue('Uploads: a real PNG is accepted', is_file(UPLOAD_PATH . '/' . $stored['path']));
        $this->assertSame('Uploads: the MIME type is recorded from content', 'image/png', (string) $stored['mime']);
        $this->assertGreaterThan('Uploads: the stored image has size', 100, (float) $stored['size']);

        if ($stored['thumb_path'] !== null) {
            $this->written[] = UPLOAD_PATH . '/' . $stored['thumb_path'];
            $this->assertTrue('Uploads: a thumbnail is generated', is_file(UPLOAD_PATH . '/' . $stored['thumb_path']));
        }
        if ($stored['webp_path'] !== null && $stored['webp_path'] !== $stored['path']) {
            $this->written[] = UPLOAD_PATH . '/' . $stored['webp_path'];
            $this->assertTrue('Uploads: a WebP copy is generated', is_file(UPLOAD_PATH . '/' . $stored['webp_path']));
        }

        // Re-encoding strips anything smuggled inside the original bytes.
        $payload = $this->pngBytes(32, 32) . '<?php echo "pwned"; ?>';
        $polyglot = $this->media->storeImage($this->upload('polyglot.png', $payload), 'suite');
        $this->written[] = UPLOAD_PATH . '/' . $polyglot['path'];
        $contents = (string) file_get_contents(UPLOAD_PATH . '/' . $polyglot['path']);
        $this->assertNotContains('Uploads: re-encoding removes appended PHP', '<?php', $contents);
    }

    private function rejectsDisguisedFiles(): void
    {
        // A PHP script with an image extension must not be stored.
        $this->assertThrows(
            'Uploads: a PHP file named .png is rejected',
            fn () => $this->media->storeImage($this->upload('shell.png', "<?php system(\$_GET['c']); ?>"), 'suite')
        );

        // A double extension is no help either.
        $this->assertThrows(
            'Uploads: shell.php.png is rejected',
            fn () => $this->media->storeImage($this->upload('shell.php.png', '<?php echo 1;'), 'suite')
        );

        // An SVG can carry script, so it is not accepted as a photo.
        $this->assertThrows(
            'Uploads: an SVG is not accepted as a photo',
            fn () => $this->media->storeImage(
                $this->upload('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
                'suite'
            )
        );

        // An HTML file renamed to .jpg is rejected on content, not name.
        $this->assertThrows(
            'Uploads: HTML renamed .jpg is rejected',
            fn () => $this->media->storeImage($this->upload('page.jpg', '<html><body>hi</body></html>'), 'suite')
        );

        // Audio is checked the same way.
        $this->assertThrows(
            'Uploads: a fake MP3 is rejected',
            fn () => $this->media->storeAudio($this->upload('song.mp3', 'not audio at all'), 'suite')
        );

        // Fonts too.
        $this->assertThrows(
            'Uploads: a fake font is rejected',
            fn () => $this->media->storeFont($this->upload('evil.ttf', '<?php echo 1;'))
        );
    }

    private function rejectsOversizedFiles(): void
    {
        $file = $this->upload('huge.png', 'x');
        $file['size'] = 50 * 1024 * 1024;
        $this->assertThrows(
            'Uploads: an oversized file is rejected before it is read',
            fn () => $this->media->storeImage($file, 'suite')
        );

        $failed = $this->upload('failed.png', 'x');
        $failed['error'] = UPLOAD_ERR_INI_SIZE;
        $this->assertThrows(
            'Uploads: a PHP upload error is reported clearly',
            fn () => $this->media->storeImage($failed, 'suite'),
            'larger'
        );
    }

    private function safeNaming(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->pass('Uploads: skipped naming checks, GD is not installed');
            return;
        }

        $stored = $this->media->storeImage(
            $this->upload('../../etc/pass wd;rm -rf.png', $this->pngBytes()),
            '../../secrets'
        );
        $this->written[] = UPLOAD_PATH . '/' . $stored['path'];

        $this->assertNotContains('Uploads: the stored path has no traversal', '..', (string) $stored['path']);
        $this->assertNotContains(
            'Uploads: the original filename is not reused on disk',
            'pass',
            (string) $stored['path']
        );
        $this->assertMatches(
            'Uploads: the stored name is random and safe',
            '#^[a-z0-9_\-]+/\d{4}/\d{2}/[a-z0-9\-]+\.(png|jpg|jpeg|webp|gif)$#',
            (string) $stored['path']
        );
        $this->assertTrue(
            'Uploads: the file lands inside the uploads directory',
            str_starts_with(
                (string) realpath(UPLOAD_PATH . '/' . $stored['path']),
                (string) realpath(UPLOAD_PATH) . DIRECTORY_SEPARATOR
            )
        );
        $this->assertNotContains(
            'Uploads: the recorded original name is sanitised',
            '..',
            (string) $stored['original_name']
        );

        // Two uploads of the same file never collide.
        $second = $this->media->storeImage($this->upload('same.png', $this->pngBytes()), 'suite');
        $this->written[] = UPLOAD_PATH . '/' . $second['path'];
        $this->assertFalse('Uploads: names do not collide', $stored['path'] === $second['path']);
    }

    private function uploadDirectoryCannotExecutePhp(): void
    {
        $htaccess = UPLOAD_PATH . '/.htaccess';
        $this->assertTrue('Uploads: the directory carries an .htaccess', is_file($htaccess));

        $rules = (string) file_get_contents($htaccess);
        $this->assertContains('Uploads: the PHP engine is switched off', 'engine off', strtolower($rules));
        $this->assertContains('Uploads: PHP handlers are removed', 'removehandler', strtolower($rules));
        $this->assertContains('Uploads: .php is denied outright', '.php', strtolower($rules));
    }

    private function inUseProtection(): void
    {
        $db = Database::instance();
        $repository = new MediaRepository();

        $invitationId = (int) $db->value(
            'SELECT id FROM ' . $db->wrap($db->table('invitations')) . ' WHERE deleted_at IS NULL LIMIT 1',
            [],
            0
        );
        if ($invitationId === 0 || !function_exists('imagecreatetruecolor')) {
            $this->pass('Media: skipped in-use protection, nothing to attach to');
            return;
        }

        $stored = $this->media->storeImage($this->upload('in-use.png', $this->pngBytes()), 'suite');
        $this->written[] = UPLOAD_PATH . '/' . $stored['path'];

        $mediaId = $repository->create([
            'user_id'       => null,
            'kind'          => 'image',
            'path'          => $stored['path'],
            'thumb_path'    => $stored['thumb_path'],
            'webp_path'     => $stored['webp_path'],
            'original_name' => $stored['original_name'],
            'mime'          => $stored['mime'],
            'extension'     => pathinfo((string) $stored['path'], PATHINFO_EXTENSION),
            'size'          => $stored['size'],
            'width'         => $stored['width'],
            'height'        => $stored['height'],
            'folder'        => 'suite',
            'is_library'    => 1,
        ]);

        // Attach it to an invitation so it counts as in use.
        $photoId = (new \App\Repositories\InvitationPhotoRepository())->create([
            'invitation_id' => $invitationId,
            'path'          => $stored['path'],
            'thumb_path'    => $stored['thumb_path'],
            'role'          => 'gallery',
            'sort_order'    => 999,
        ]);

        $refused = $this->media->deleteMedia($mediaId, false);
        $this->assertFalse('Media: deleting an in-use asset is refused', (bool) $refused['ok']);
        $this->assertTrue('Media: the refusal explains where it is used', ($refused['usage'] ?? []) !== []);
        $this->assertTrue('Media: the file is still on disk', is_file(UPLOAD_PATH . '/' . $stored['path']));

        $forced = $this->media->deleteMedia($mediaId, true);
        $this->assertTrue('Media: an explicit confirmation deletes it', (bool) $forced['ok'], (string) $forced['message']);
        $this->assertFalse('Media: the file is gone once confirmed', is_file(UPLOAD_PATH . '/' . $stored['path']));

        $db->delete('invitation_photos', ['id' => $photoId]);
    }

    private function cleanUp(): void
    {
        foreach (array_unique($this->written) as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        // Remove the empty suite folders we may have created.
        foreach (glob(UPLOAD_PATH . '/suite/*/*') ?: [] as $dir) {
            if (is_dir($dir)) {
                @rmdir($dir);
            }
        }
        foreach ([UPLOAD_PATH . '/suite'] as $dir) {
            foreach (glob($dir . '/*') ?: [] as $child) {
                if (is_dir($child)) {
                    @rmdir($child);
                }
            }
            @rmdir($dir);
        }
    }
}
