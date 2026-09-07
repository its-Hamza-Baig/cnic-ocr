<?php

namespace App\Services;

class CnicNormalizerService
{
    public function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if (strlen($digits) === 13) {
            return substr($digits, 0, 5).'-'.substr($digits, 5, 7).'-'.substr($digits, 12, 1);
        }

        return $trimmed;
    }

    public function isValid(?string $value): bool
    {
        $normalized = $this->normalize($value);

        if ($normalized === null) {
            return false;
        }

        return (bool) preg_match((string) config('cnic.pattern'), $normalized);
    }
}
