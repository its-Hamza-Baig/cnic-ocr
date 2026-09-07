<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CnicImageStorage
{
    public function storeTemporary(UploadedFile $file, string $side): string
    {
        $name = $side.'-'.Str::uuid().'.'.$this->extension($file);

        return $file->storeAs('tmp', $name, [
            'disk' => $this->disk(),
            'visibility' => 'private',
        ]);
    }

    public function promote(string $temporaryPath, int $personId, string $side): string
    {
        $disk = Storage::disk($this->disk());
        $extension = pathinfo($temporaryPath, PATHINFO_EXTENSION) ?: 'jpg';
        $destination = 'persons/'.$personId.'/'.$side.'-'.Str::uuid().'.'.$extension;

        if ($disk->exists($temporaryPath)) {
            $disk->makeDirectory('persons/'.$personId);
            $disk->move($temporaryPath, $destination);
        }

        return $destination;
    }

    public function absolutePath(string $path): string
    {
        return Storage::disk($this->disk())->path($path);
    }

    public function delete(?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        Storage::disk($this->disk())->delete($path);
    }

    public function disk(): string
    {
        return (string) config('cnic.disk', 'cnic');
    }

    private function extension(UploadedFile $file): string
    {
        $mime = $file->getMimeType();

        return match ($mime) {
            'image/png' => 'png',
            default => 'jpg',
        };
    }
}
