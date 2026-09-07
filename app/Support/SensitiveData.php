<?php

namespace App\Support;

final class SensitiveData
{
    public static function maskCnic(?string $cnic): ?string
    {
        if ($cnic === null || $cnic === '') {
            return $cnic;
        }

        $digits = preg_replace('/\D+/', '', $cnic) ?? '';

        if (strlen($digits) !== 13) {
            return '***';
        }

        return substr($digits, 0, 5).'-*****'.substr($digits, 10, 2).'-'.substr($digits, 12, 1);
    }

    public static function redact(string $value): string
    {
        $value = preg_replace('/\b\d{5}-\d{7}-\d\b/', '*****', $value) ?? $value;

        return preg_replace('/\b\d{13}\b/', '*****', $value) ?? $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function redactArray(array $data): array
    {
        $sensitiveKeys = [
            'cnic',
            'name',
            'father_husband_name',
            'address',
            'raw_text',
            'raw_text_encrypted',
            'password',
        ];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::redactArray($value);

                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            if (in_array((string) $key, $sensitiveKeys, true)) {
                $data[$key] = '[redacted]';

                continue;
            }

            $data[$key] = self::redact($value);
        }

        return $data;
    }
}
