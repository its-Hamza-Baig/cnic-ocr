<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class CnicImageRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('A valid image file is required.');

            return;
        }

        $maxKb = (int) config('ocr.max_upload_kb', 5120);

        if ($value->getSize() === false || (int) $value->getSize() > $maxKb * 1024) {
            $fail("The image may not be greater than {$maxKb} kilobytes.");

            return;
        }

        $allowedMimes = config('ocr.allowed_mimes', ['image/jpeg', 'image/png']);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detected = $finfo ? finfo_file($finfo, $value->getRealPath()) : false;

        if ($finfo) {
            finfo_close($finfo);
        }

        if (! is_string($detected) || ! in_array($detected, $allowedMimes, true)) {
            $fail('The file must be a JPEG or PNG image.');

            return;
        }

        $imageInfo = @getimagesize($value->getRealPath());

        if ($imageInfo === false) {
            $fail('The file contents are not a valid image.');
        }
    }
}
