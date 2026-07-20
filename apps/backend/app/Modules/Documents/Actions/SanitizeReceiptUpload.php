<?php

declare(strict_types=1);

namespace App\Modules\Documents\Actions;

use App\Modules\Documents\Data\SanitizedReceipt;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class SanitizeReceiptUpload
{
    private const MAX_BYTES = 10 * 1024 * 1024;

    private const MAX_PIXELS = 25_000_000;

    /** @var array<string, string> */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
    ];

    public function execute(UploadedFile $file): SanitizedReceipt
    {
        $source = $file->getRealPath();
        $binary = is_string($source) ? file_get_contents($source) : false;
        if (! is_string($binary) || $binary === '' || strlen($binary) > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'receipt' => ['The receipt must be a non-empty file no larger than 10 MB.'],
            ]);
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);
        if (! is_string($mimeType) || ! array_key_exists($mimeType, self::EXTENSIONS)) {
            throw ValidationException::withMessages([
                'receipt' => ['Only content-verified JPG, PNG, and PDF receipts are accepted.'],
            ]);
        }

        if ($mimeType === 'image/jpeg' || $mimeType === 'image/png') {
            $binary = $this->sanitizeImage($binary, $mimeType);
        }

        $extension = self::EXTENSIONS[$mimeType];

        return new SanitizedReceipt(
            binary: $binary,
            mimeType: $mimeType,
            extension: $extension,
            displayFilename: $this->displayFilename($file->getClientOriginalName(), $extension),
            sizeBytes: strlen($binary),
            sha256: hash('sha256', $binary),
        );
    }

    private function sanitizeImage(string $binary, string $mimeType): string
    {
        $dimensions = getimagesizefromstring($binary);
        if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1
            || $dimensions[0] > 10_000 || $dimensions[1] > 10_000
            || $dimensions[0] * $dimensions[1] > self::MAX_PIXELS) {
            throw ValidationException::withMessages([
                'receipt' => ['The image dimensions are invalid or exceed the 25 megapixel limit.'],
            ]);
        }

        $image = imagecreatefromstring($binary);
        if ($image === false) {
            throw ValidationException::withMessages(['receipt' => ['The receipt image could not be decoded.']]);
        }

        ob_start();
        $written = $mimeType === 'image/jpeg'
            ? imagejpeg($image, null, 90)
            : imagepng($image, null, 6);
        $sanitized = ob_get_clean();
        imagedestroy($image);

        if (! $written || $sanitized === '') {
            throw ValidationException::withMessages(['receipt' => ['The receipt image could not be sanitized.']]);
        }

        return $sanitized;
    }

    private function displayFilename(string $original, string $extension): string
    {
        $basename = pathinfo(str_replace('\\', '/', $original), PATHINFO_FILENAME);
        $safe = preg_replace('/[^A-Za-z0-9._ -]+/', '-', $basename);
        $safe = is_string($safe) ? trim($safe, '.-_ ') : '';
        $safe = mb_substr($safe, 0, 180);

        return ($safe === '' ? 'receipt' : $safe).'.'.$extension;
    }
}
