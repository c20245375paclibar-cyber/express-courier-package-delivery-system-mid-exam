<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ImageUploadService
{
    private const MAX_OUTPUT_BYTES = 2 * 1024 * 1024;

    public function store(
        UploadedFile $file,
        string $directory,
        int $maxDimension,
        bool $square = false,
        string $errorKey = 'image',
    ): string {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            throw new RuntimeException('Image uploads require GD with WebP support.');
        }

        $source = imagecreatefromstring($file->getContent());
        if (! $source instanceof GdImage) {
            throw ValidationException::withMessages([$errorKey => 'The selected image could not be read.']);
        }

        $optimized = null;
        try {
            if (imagesx($source) > 4096 || imagesy($source) > 4096) {
                throw ValidationException::withMessages([$errorKey => 'Image dimensions cannot exceed 4096 by 4096 pixels.']);
            }

            $optimized = $this->resize($source, $maxDimension, $square);
            $contents = $this->encodeWebp($optimized, $errorKey);
        } finally {
            imagedestroy($source);
            if ($optimized instanceof GdImage) {
                imagedestroy($optimized);
            }
        }

        $path = trim($directory, '/').'/'.Str::uuid().'.webp';
        if (! Storage::disk('public')->put($path, $contents)) {
            throw new RuntimeException('The optimized image could not be saved.');
        }

        return $path;
    }

    private function resize(GdImage $source, int $maxDimension, bool $square): GdImage
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        if ($square) {
            $cropSize = min($sourceWidth, $sourceHeight);
            $cropX = (int) floor(($sourceWidth - $cropSize) / 2);
            $cropY = (int) floor(($sourceHeight - $cropSize) / 2);
            $width = $maxDimension;
            $height = $maxDimension;
        } else {
            $scale = min(1, $maxDimension / max($sourceWidth, $sourceHeight));
            $cropX = 0;
            $cropY = 0;
            $cropSize = 0;
            $width = max(1, (int) round($sourceWidth * $scale));
            $height = max(1, (int) round($sourceHeight * $scale));
        }

        $image = imagecreatetruecolor($width, $height);
        if (! $image instanceof GdImage) {
            throw new RuntimeException('An image canvas could not be created.');
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        $copied = $square
            ? imagecopyresampled($image, $source, 0, 0, $cropX, $cropY, $width, $height, $cropSize, $cropSize)
            : imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        if (! $copied) {
            throw new RuntimeException('The uploaded image could not be resized.');
        }

        return $image;
    }

    private function encodeWebp(GdImage $image, string $errorKey): string
    {
        foreach ([82, 72, 62, 52, 42] as $quality) {
            ob_start();
            $encoded = imagewebp($image, null, $quality);
            $contents = ob_get_clean();

            if (! $encoded || ! is_string($contents)) {
                throw new RuntimeException('The uploaded image could not be converted to WebP.');
            }

            if (strlen($contents) <= self::MAX_OUTPUT_BYTES) {
                return $contents;
            }
        }

        throw ValidationException::withMessages([
            $errorKey => 'This image could not be compressed to 2 MB. Try a smaller image.',
        ]);
    }
}
