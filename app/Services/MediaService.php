<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Image\ImageProcessor;
use App\Core\Logger;
use App\Repositories\MediaRepository;
use App\Repositories\UserRepository;

/**
 * Upload handling for photos, audio and fonts.
 *
 * Security rules applied to every single upload:
 *   - is_uploaded_file() and the PHP error code are checked first
 *   - the real MIME type is read from the file contents (finfo), never from
 *     the browser-supplied Content-Type or the extension
 *   - the extension is then derived from the verified type, so ".php.jpg"
 *     cannot survive
 *   - the stored filename is random, so nothing is guessable or overwritable
 *   - images are re-encoded through GD, which discards any embedded payload
 *   - size limits are enforced against the real file size
 *   - files land under /uploads, which is configured to never execute
 */
final class MediaService
{
    /** Verified MIME type => the only extension we will write. */
    private const IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    private const AUDIO_TYPES = [
        'audio/mpeg' => 'mp3',
        'audio/mp3'  => 'mp3',
        'audio/ogg'  => 'ogg',
        'audio/wav'  => 'wav',
        'audio/x-wav' => 'wav',
    ];

    private const FONT_TYPES = [
        'font/ttf'                => 'ttf',
        'font/otf'                => 'otf',
        'application/font-sfnt'   => 'ttf',
        'application/x-font-ttf'  => 'ttf',
        'application/vnd.ms-opentype' => 'otf',
    ];

    public function __construct(
        private readonly MediaRepository $media = new MediaRepository()
    ) {
    }

    /**
     * Validate and store an uploaded image.
     *
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file
     * @param string $folder subdirectory under /uploads
     *
     * @return array{path:string,thumb_path:string|null,webp_path:string|null,width:int,height:int,size:int,mime:string,original_name:string}
     *
     * @throws \RuntimeException with a message safe to show the user
     */
    public function storeImage(array $file, string $folder = 'photos', bool $makeThumb = true): array
    {
        $this->assertUploadOk($file);

        $maxSize = (int) Config::get('uploads.max_image_size', 8388608);
        $this->assertSize($file, $maxSize);

        $mime = $this->detectMime($file['tmp_name']);
        if (!isset(self::IMAGE_TYPES[$mime])) {
            throw new \RuntimeException('Please upload a JPG, PNG, WebP or GIF image.');
        }

        $probe = ImageProcessor::probe($file['tmp_name']);
        if ($probe === null || $probe['width'] < 8 || $probe['height'] < 8) {
            throw new \RuntimeException('That file is not a readable image.');
        }
        // A decompression-bomb guard: 80 MP is far beyond any real photo.
        if ($probe['width'] * $probe['height'] > 80000000) {
            throw new \RuntimeException('That image is too large. Please resize it and try again.');
        }

        $extension = self::IMAGE_TYPES[$mime];
        $folder = $this->safeFolder($folder);
        $basename = $this->randomName();

        $relativeDir = $folder . '/' . date('Y/m');
        $absoluteDir = UPLOAD_PATH . '/' . $relativeDir;
        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            throw new \RuntimeException('The upload directory is not writable.');
        }

        $relativePath = $relativeDir . '/' . $basename . '.' . $extension;
        $absolutePath = UPLOAD_PATH . '/' . $relativePath;

        // Re-encode rather than move: normalises the file and strips anything
        // hidden inside the original bytes.
        $maxWidth = (int) Config::get('uploads.image_max_width', 2000);
        $result = ImageProcessor::resizeTo($file['tmp_name'], $absolutePath, $maxWidth);
        if ($result === null) {
            throw new \RuntimeException('The image could not be processed. Please try another file.');
        }

        $thumbPath = null;
        if ($makeThumb) {
            $thumbRelative = $relativeDir . '/' . $basename . '-thumb.' . $extension;
            $thumb = ImageProcessor::thumbnail(
                $absolutePath,
                UPLOAD_PATH . '/' . $thumbRelative,
                (int) Config::get('uploads.thumb_width', 480)
            );
            $thumbPath = $thumb === null ? null : $thumbRelative;
        }

        $webpRelative = $relativeDir . '/' . $basename . '.webp';
        $webpPath = $extension === 'webp'
            ? $relativePath
            : (ImageProcessor::toWebp($absolutePath, UPLOAD_PATH . '/' . $webpRelative) === null ? null : $webpRelative);

        return [
            'path'          => $relativePath,
            'thumb_path'    => $thumbPath,
            'webp_path'     => $webpPath,
            'width'         => $result['width'],
            'height'        => $result['height'],
            'size'          => $result['size'],
            'mime'          => $mime,
            'original_name' => $this->safeOriginalName($file['name']),
        ];
    }

    /**
     * Validate and store an uploaded audio track.
     *
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file
     * @return array{path:string,size:int,mime:string,original_name:string}
     */
    public function storeAudio(array $file, string $folder = 'music'): array
    {
        $this->assertUploadOk($file);
        $this->assertSize($file, (int) Config::get('uploads.max_music_size', 10485760));

        $mime = $this->detectMime($file['tmp_name']);
        if (!isset(self::AUDIO_TYPES[$mime])) {
            throw new \RuntimeException('Please upload an MP3, OGG or WAV file.');
        }

        // An MP3 must start with an ID3 tag or a frame sync; this rejects a
        // renamed archive or script that finfo happened to guess as audio.
        $head = (string) file_get_contents($file['tmp_name'], false, null, 0, 4);
        if (self::AUDIO_TYPES[$mime] === 'mp3'
            && !str_starts_with($head, 'ID3')
            && !(strlen($head) >= 2 && (ord($head[0]) === 0xFF && (ord($head[1]) & 0xE0) === 0xE0))) {
            throw new \RuntimeException('That file does not look like a valid MP3.');
        }

        $relativeDir = $this->safeFolder($folder) . '/' . date('Y/m');
        $absoluteDir = UPLOAD_PATH . '/' . $relativeDir;
        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            throw new \RuntimeException('The upload directory is not writable.');
        }

        $relativePath = $relativeDir . '/' . $this->randomName() . '.' . self::AUDIO_TYPES[$mime];
        if (!move_uploaded_file($file['tmp_name'], UPLOAD_PATH . '/' . $relativePath)) {
            throw new \RuntimeException('The file could not be saved.');
        }
        @chmod(UPLOAD_PATH . '/' . $relativePath, 0644);

        return [
            'path'          => $relativePath,
            'size'          => (int) filesize(UPLOAD_PATH . '/' . $relativePath),
            'mime'          => $mime,
            'original_name' => $this->safeOriginalName($file['name']),
        ];
    }

    /**
     * Validate and store an uploaded font, verifying the sfnt signature.
     *
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file
     * @return array{path:string,size:int,mime:string,original_name:string,family:string}
     */
    public function storeFont(array $file): array
    {
        $this->assertUploadOk($file);
        $this->assertSize($file, 6291456); // 6 MB

        $extension = strtolower((string) pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['ttf', 'otf'], true)) {
            throw new \RuntimeException('Please upload a .ttf or .otf font file.');
        }

        // Font sniffing is unreliable, so check the sfnt magic ourselves.
        $magic = (string) file_get_contents($file['tmp_name'], false, null, 0, 4);
        $valid = in_array($magic, ["\x00\x01\x00\x00", 'true', 'ttcf', 'OTTO'], true);
        if (!$valid) {
            throw new \RuntimeException('That file is not a valid TrueType or OpenType font.');
        }

        $family = 'Custom Font';
        try {
            $parsed = \App\Core\Pdf\TrueTypeFont::fromFile($file['tmp_name']);
            $family = $parsed->postScriptName;
        } catch (\Throwable) {
            // A CFF/OTF font cannot be parsed for PDF use but is still fine
            // for the browser, so keep going.
        }

        $relativeDir = 'fonts';
        $absoluteDir = UPLOAD_PATH . '/' . $relativeDir;
        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            throw new \RuntimeException('The upload directory is not writable.');
        }

        $relativePath = $relativeDir . '/' . $this->randomName() . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], UPLOAD_PATH . '/' . $relativePath)) {
            throw new \RuntimeException('The font could not be saved.');
        }
        @chmod(UPLOAD_PATH . '/' . $relativePath, 0644);

        return [
            'path'          => $relativePath,
            'size'          => (int) filesize(UPLOAD_PATH . '/' . $relativePath),
            'mime'          => $extension === 'otf' ? 'font/otf' : 'font/ttf',
            'original_name' => $this->safeOriginalName($file['name']),
            'family'        => $family,
        ];
    }

    /**
     * Store an image and record it in the media library.
     *
     * @return array<string,mixed> the media row
     */
    public function storeToLibrary(array $file, ?int $userId, string $folder = 'media', bool $isLibrary = false): array
    {
        $stored = $this->storeImage($file, $folder);
        $hash = hash_file('sha256', UPLOAD_PATH . '/' . $stored['path']) ?: null;

        $id = $this->media->create([
            'user_id'       => $userId,
            'folder'        => $this->safeFolder($folder),
            'path'          => $stored['path'],
            'thumb_path'    => $stored['thumb_path'],
            'webp_path'     => $stored['webp_path'],
            'original_name' => $stored['original_name'],
            'mime'          => $stored['mime'],
            'extension'     => pathinfo($stored['path'], PATHINFO_EXTENSION),
            'size'          => $stored['size'],
            'width'         => $stored['width'],
            'height'        => $stored['height'],
            'kind'          => 'image',
            'is_library'    => $isLibrary ? 1 : 0,
            'content_hash'  => $hash,
        ]);

        if ($userId !== null) {
            (new UserRepository())->addStorageUsed($userId, $stored['size']);
        }

        $row = $this->media->find($id);
        return $row ?? $stored;
    }

    // ------------------------------------------------------------------
    //  Validation helpers
    // ------------------------------------------------------------------

    private function assertUploadOk(array $file): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException($this->uploadErrorMessage($error));
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            Logger::security('Rejected a file that was not a genuine HTTP upload');
            throw new \RuntimeException('The upload could not be verified. Please try again.');
        }
    }

    private function assertSize(array $file, int $maxBytes): void
    {
        $actual = (int) @filesize((string) $file['tmp_name']);
        if ($actual <= 0) {
            throw new \RuntimeException('The uploaded file is empty.');
        }
        if ($actual > $maxBytes) {
            throw new \RuntimeException(
                'That file is ' . \App\Core\Str::bytesToHuman($actual)
                . '. The limit is ' . \App\Core\Str::bytesToHuman($maxBytes) . '.'
            );
        }
    }

    /** The real MIME type, read from the file's own bytes. */
    public function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return strtolower($mime);
                }
            }
        }
        $probe = ImageProcessor::probe($path);
        if ($probe !== null && $probe['mime'] !== '') {
            return strtolower($probe['mime']);
        }
        return 'application/octet-stream';
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That file is larger than the server allows.',
            UPLOAD_ERR_PARTIAL   => 'The upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_FILE   => 'Please choose a file to upload.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'The server could not store the file.',
            UPLOAD_ERR_EXTENSION => 'The upload was blocked by the server.',
            default              => 'The upload failed. Please try again.',
        };
    }

    private function randomName(): string
    {
        return date('YmdHis') . '-' . bin2hex(random_bytes(8));
    }

    private function safeFolder(string $folder): string
    {
        $clean = preg_replace('/[^a-z0-9_\-]/i', '', $folder) ?? '';
        return $clean === '' ? 'media' : strtolower($clean);
    }

    private function safeOriginalName(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^\p{L}\p{N}\.\-_ ]/u', '', $name) ?? 'upload';
        return mb_substr($name, 0, 180);
    }

    // ------------------------------------------------------------------
    //  Deletion
    // ------------------------------------------------------------------

    /**
     * Delete files by their /uploads-relative path.
     *
     * Every path is resolved and checked to be inside the uploads directory,
     * so a crafted "../" value cannot reach application code.
     *
     * @param array<int,string> $relativePaths
     * @return int number of files removed
     */
    public function deleteFiles(array $relativePaths): int
    {
        $removed = 0;
        $root = realpath(UPLOAD_PATH);
        if ($root === false) {
            return 0;
        }
        foreach ($relativePaths as $relative) {
            $relative = trim($relative);
            if ($relative === '') {
                continue;
            }
            $candidate = UPLOAD_PATH . '/' . ltrim(str_replace('\\', '/', $relative), '/');
            $real = realpath($candidate);
            if ($real === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
                Logger::security('Blocked a file deletion outside the uploads directory', [
                    'path' => $relative,
                ]);
                continue;
            }
            if (is_file($real) && @unlink($real)) {
                $removed++;
            }
        }
        return $removed;
    }

    /** Delete a media library entry and, when unused, its files. */
    public function deleteMedia(int $mediaId, bool $force = false): array
    {
        $row = $this->media->find($mediaId);
        if ($row === null) {
            return ['ok' => false, 'message' => 'That file no longer exists.'];
        }
        $usage = $this->media->usage($row);
        if ($usage['total'] > 0 && !$force) {
            return [
                'ok'      => false,
                'message' => 'This file is still used by ' . $usage['total']
                    . ' item(s). Confirm to delete it anyway.',
                'usage'   => $usage,
            ];
        }

        $this->deleteFiles([
            (string) $row['path'],
            (string) ($row['thumb_path'] ?? ''),
            (string) ($row['webp_path'] ?? ''),
        ]);
        $this->media->forceDelete($mediaId);

        if ($row['user_id'] !== null) {
            (new UserRepository())->addStorageUsed((int) $row['user_id'], -(int) $row['size']);
        }

        AuditService::instance()->log('media.delete', 'media', $mediaId, (string) $row['path']);

        return ['ok' => true, 'message' => 'File deleted.'];
    }

    /** Absolute path for a stored upload, or null when outside /uploads. */
    public function absolutePath(string $relative): ?string
    {
        $root = realpath(UPLOAD_PATH);
        if ($root === false) {
            return null;
        }
        $real = realpath(UPLOAD_PATH . '/' . ltrim(str_replace('\\', '/', $relative), '/'));
        if ($real === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return $real;
    }
}
