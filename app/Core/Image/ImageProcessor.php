<?php

declare(strict_types=1);

namespace App\Core\Image;

use App\Core\Config;
use App\Core\Logger;

/**
 * GD-based image pipeline for user uploads.
 *
 * Every uploaded image is re-encoded rather than copied. That both normalises
 * the output (size limits, EXIF rotation, WebP companion, thumbnail) and
 * destroys anything hidden inside the original file, which is the cheapest
 * real defence against polyglot upload attacks.
 */
final class ImageProcessor
{
    /** @return array{width:int,height:int,mime:string,type:int}|null */
    public static function probe(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $info = @getimagesize($path);
        if ($info === false) {
            return null;
        }
        return [
            'width'  => (int) $info[0],
            'height' => (int) $info[1],
            'mime'   => (string) ($info['mime'] ?? ''),
            'type'   => (int) $info[2],
        ];
    }

    public static function isSupported(string $mime): bool
    {
        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
    }

    /** @return \GdImage|null */
    private static function load(string $path): ?\GdImage
    {
        $probe = self::probe($path);
        if ($probe === null) {
            return null;
        }
        $image = match ($probe['type']) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_GIF  => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default        => false,
        };
        if ($image === false) {
            return null;
        }
        return self::autoRotate($image, $path, $probe['type']);
    }

    /** Honour the EXIF orientation flag so phone photos are upright. */
    private static function autoRotate(\GdImage $image, string $path, int $type): \GdImage
    {
        if ($type !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        if ($orientation <= 1) {
            return $image;
        }
        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };
        if ($rotated instanceof \GdImage) {
            imagedestroy($image);
            return $rotated;
        }
        return $image;
    }

    /**
     * Resize (never upscale) and save as JPEG or PNG.
     *
     * @return array{path:string,width:int,height:int,size:int}|null
     */
    public static function resizeTo(
        string $source,
        string $destination,
        int $maxWidth,
        int $maxHeight = 0,
        ?int $quality = null
    ): ?array {
        $image = self::load($source);
        if ($image === null) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $maxHeight = $maxHeight > 0 ? $maxHeight : (int) round($maxWidth * 4);

        $scale = min(1.0, $maxWidth / max(1, $width), $maxHeight / max(1, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = self::blankCanvas($targetWidth, $targetHeight, self::hasAlpha($source));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($image);

        $saved = self::save($canvas, $destination, $quality);
        imagedestroy($canvas);

        if (!$saved) {
            return null;
        }
        return [
            'path'   => $destination,
            'width'  => $targetWidth,
            'height' => $targetHeight,
            'size'   => (int) filesize($destination),
        ];
    }

    /**
     * Square, centre-cropped thumbnail - what the galleries and template
     * cards use.
     *
     * @return array{path:string,width:int,height:int,size:int}|null
     */
    public static function thumbnail(string $source, string $destination, int $size = 480, ?int $quality = null): ?array
    {
        $image = self::load($source);
        if ($image === null) {
            return null;
        }
        $width = imagesx($image);
        $height = imagesy($image);

        $side = min($width, $height);
        $sourceX = (int) round(($width - $side) / 2);
        $sourceY = (int) round(($height - $side) / 2);

        $canvas = self::blankCanvas($size, $size, self::hasAlpha($source));
        imagecopyresampled($canvas, $image, 0, 0, $sourceX, $sourceY, $size, $size, $side, $side);
        imagedestroy($image);

        $saved = self::save($canvas, $destination, $quality);
        imagedestroy($canvas);

        if (!$saved) {
            return null;
        }
        return [
            'path'   => $destination,
            'width'  => $size,
            'height' => $size,
            'size'   => (int) filesize($destination),
        ];
    }

    /** Write a WebP companion for the <picture> element. Null when unsupported. */
    public static function toWebp(string $source, string $destination, ?int $quality = null): ?string
    {
        if (!function_exists('imagewebp')) {
            return null;
        }
        $image = self::load($source);
        if ($image === null) {
            return null;
        }
        $quality ??= (int) Config::get('uploads.webp_quality', 82);
        self::ensureDirectory($destination);
        $ok = @imagewebp($image, $destination, max(1, min(100, $quality)));
        imagedestroy($image);
        return $ok ? $destination : null;
    }

    /** Average colour of an image - used to pick a matching placeholder. */
    public static function averageColor(string $path): string
    {
        $image = self::load($path);
        if ($image === null) {
            return '#DDD5C7';
        }
        $small = imagecreatetruecolor(1, 1);
        imagecopyresampled($small, $image, 0, 0, 0, 0, 1, 1, imagesx($image), imagesy($image));
        $color = imagecolorat($small, 0, 0);
        imagedestroy($small);
        imagedestroy($image);
        return sprintf('#%02X%02X%02X', ($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF);
    }

    private static function hasAlpha(string $path): bool
    {
        $probe = self::probe($path);
        return $probe !== null && in_array($probe['type'], [IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true);
    }

    private static function blankCanvas(int $width, int $height, bool $alpha): \GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        if ($alpha) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
            imagealphablending($canvas, true);
        } else {
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        }
        return $canvas;
    }

    private static function save(\GdImage $image, string $destination, ?int $quality): bool
    {
        self::ensureDirectory($destination);
        $extension = strtolower(pathinfo($destination, PATHINFO_EXTENSION));

        try {
            return match ($extension) {
                'png'  => imagepng($image, $destination, 8),
                'webp' => function_exists('imagewebp')
                    && imagewebp($image, $destination, $quality ?? (int) Config::get('uploads.webp_quality', 82)),
                'gif'  => imagegif($image, $destination),
                default => imagejpeg(
                    $image,
                    $destination,
                    max(1, min(100, $quality ?? (int) Config::get('uploads.jpeg_quality', 86)))
                ),
            };
        } catch (\Throwable $e) {
            Logger::warning('Image save failed: ' . $e->getMessage(), ['destination' => basename($destination)]);
            return false;
        }
    }

    private static function ensureDirectory(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    /** Placeholder SVG shown while a real image lazy-loads. */
    public static function placeholderDataUri(string $color = '#EFE3D2'): string
    {
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? $color : '#EFE3D2';
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="8" height="8">'
            . '<rect width="8" height="8" fill="' . $color . '"/></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
