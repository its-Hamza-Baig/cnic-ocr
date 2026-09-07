<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class PendingScanSession
{
    public const KEY = 'pending_cnic_scan';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function put(array $payload): void
    {
        session([self::KEY => $payload]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(): ?array
    {
        $payload = session(self::KEY);

        return is_array($payload) ? $payload : null;
    }

    public function forget(): void
    {
        $payload = $this->get();

        session()->forget(self::KEY);

        if (! is_array($payload)) {
            return;
        }

        foreach (['front_image_path', 'back_image_path'] as $key) {
            $path = $payload[$key] ?? null;

            if (is_string($path) && $path !== '' && str_starts_with($path, 'tmp/')) {
                Storage::disk((string) config('cnic.disk', 'cnic'))->delete($path);
            }
        }
    }

    public function exists(): bool
    {
        return $this->get() !== null;
    }
}
