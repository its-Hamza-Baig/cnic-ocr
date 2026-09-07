<?php

namespace App\Services\Ocr\Providers;

use App\Contracts\OcrProviderInterface;
use App\DTOs\OcrProviderResult;
use App\Exceptions\OcrProviderException;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class TesseractOcrProvider implements OcrProviderInterface
{
    public function name(): string
    {
        return 'tesseract';
    }

    public function recognize(string $absoluteImagePath, string $side = 'front'): OcrProviderResult
    {
        if (! is_file($absoluteImagePath)) {
            throw new OcrProviderException('The CNIC image could not be read for OCR.');
        }

        $workDir = sys_get_temp_dir().'/cnic-ocr-'.Str::uuid();
        mkdir($workDir, 0700, true);

        try {
            $outputs = [];

            foreach ($this->regionImages($absoluteImagePath, $workDir, $side) as $region => $path) {
                foreach ($this->languagesFor($region) as $language) {
                    foreach ($this->psmModesFor($region) as $psm) {
                        $outputs[] = $this->runTesseract($path, $language, $psm);
                    }
                }

                if ($region === 'identity') {
                    $outputs[] = $this->runTesseract($path, 'eng', '6', '0123456789-');
                }

                if ($region === 'mrz') {
                    $outputs[] = $this->runTesseract($path, 'eng', '6', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789<');
                }
            }

            $text = trim(implode("\n", array_filter(array_map('trim', $outputs))));

            if ($text === '') {
                throw new OcrProviderException('Tesseract did not find any text on the CNIC image.');
            }

            return new OcrProviderResult(
                text: $text,
                confidence: null,
                provider: $this->name(),
            );
        } finally {
            $this->deleteDirectory($workDir);
        }
    }

    /**
     * @return array<string, string>
     */
    private function regionImages(string $absoluteImagePath, string $workDir, string $side): array
    {
        $source = $this->loadImage($absoluteImagePath);

        if ($source === false) {
            $copy = $workDir.'/full.jpg';
            copy($absoluteImagePath, $copy);

            return ['full' => $copy];
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $paths = ['full' => $this->writeJpeg($source, $workDir.'/full.jpg')];

        if ($side === 'back') {
            $paths['address'] = $this->writeCrop($source, $workDir.'/address.jpg', $width, $height, 0.03, 0.12, 0.58, 0.50);
            $paths['mrz'] = $this->writeCrop($source, $workDir.'/mrz.jpg', $width, $height, 0.02, 0.72, 0.96, 0.26, enhance: true);
        } else {
            $paths['details'] = $this->writeCrop($source, $workDir.'/details.jpg', $width, $height, 0.03, 0.16, 0.58, 0.58);
            $paths['name_en'] = $this->writeCrop($source, $workDir.'/name-en.jpg', $width, $height, 0.16, 0.20, 0.42, 0.18, enhance: true);
            $paths['father_en'] = $this->writeCrop($source, $workDir.'/father-en.jpg', $width, $height, 0.16, 0.34, 0.42, 0.18, enhance: true);
            $paths['name_ur'] = $this->writeCrop($source, $workDir.'/name-ur.jpg', $width, $height, 0.50, 0.20, 0.26, 0.34, enhance: true);
            $paths['identity'] = $this->writeCrop($source, $workDir.'/identity.jpg', $width, $height, 0.02, 0.66, 0.58, 0.32, enhance: true);
        }

        imagedestroy($source);

        return array_filter($paths);
    }

    /**
     * @return list<string>
     */
    private function psmModesFor(string $region): array
    {
        return match ($region) {
            'identity', 'mrz' => ['6'],
            'name_en', 'father_en', 'name_ur', 'details', 'address' => ['6'],
            default => ['4'],
        };
    }

    /**
     * @return list<string>
     */
    private function languagesFor(string $region): array
    {
        if (in_array($region, ['mrz', 'identity', 'name_en', 'father_en'], true)) {
            return ['eng'];
        }

        if ($region === 'name_ur') {
            return ['urd'];
        }

        return $this->scriptLanguages();
    }

    /**
     * @return list<string>
     */
    private function scriptLanguages(): array
    {
        $parts = [];

        foreach ([
            (string) config('ocr.tesseract.scripts', 'eng,urd'),
            (string) config('ocr.tesseract.language', 'eng'),
            (string) config('ocr.tesseract.back_language', 'urd'),
        ] as $source) {
            foreach (preg_split('/[+,]/', $source) ?: [] as $part) {
                $part = strtolower(trim($part));

                if ($part !== '') {
                    $parts[] = $part;
                }
            }
        }

        $parts = array_values(array_unique($parts));

        return $parts !== [] ? $parts : ['eng', 'urd'];
    }

    private function loadImage(string $path): \GdImage|false
    {
        $mime = @mime_content_type($path) ?: '';

        return match (true) {
            str_contains($mime, 'png') => @imagecreatefrompng($path),
            default => @imagecreatefromjpeg($path),
        };
    }

    private function writeCrop(\GdImage $source, string $destination, int $width, int $height, float $x, float $y, float $w, float $h, bool $enhance = false): ?string
    {
        $crop = imagecrop($source, [
            'x' => (int) round($width * $x),
            'y' => (int) round($height * $y),
            'width' => max(1, (int) round($width * $w)),
            'height' => max(1, (int) round($height * $h)),
        ]);

        if ($crop === false) {
            return null;
        }

        $path = $enhance
            ? $this->writeEnhancedJpeg($crop, $destination)
            : $this->writeJpeg($crop, $destination);
        imagedestroy($crop);

        return $path;
    }

    private function writeJpeg(\GdImage $image, string $destination): string
    {
        imagejpeg($image, $destination, 95);

        return $destination;
    }

    private function writeEnhancedJpeg(\GdImage $image, string $destination): string
    {
        $width = imagesx($image) * 2;
        $height = imagesy($image) * 2;
        $scaled = imagecreatetruecolor($width, $height);
        imagecopyresampled($scaled, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
        imagefilter($scaled, IMG_FILTER_GRAYSCALE);
        imagefilter($scaled, IMG_FILTER_CONTRAST, -25);
        imagejpeg($scaled, $destination, 95);
        imagedestroy($scaled);

        return $destination;
    }

    private function runTesseract(string $imagePath, string $language, string $psm, ?string $whitelist = null): string
    {
        $timeout = max(30, (int) config('ocr.timeout_seconds', 60));
        $binary = (string) config('ocr.tesseract.binary', 'tesseract');
        $arguments = $this->tesseractArguments($imagePath, $language, $psm, $whitelist);

        if ($this->binaryAvailable($binary)) {
            $result = Process::timeout($timeout)->run(array_merge([$binary], $arguments));

            return $result->successful() ? $result->output() : '';
        }

        if (! $this->binaryAvailable('docker')) {
            throw new OcrProviderException('Tesseract is not installed. Install tesseract-ocr or build the cnic-ocr-tesseract Docker image.');
        }

        $image = (string) config('ocr.tesseract.docker_image', 'cnic-ocr-tesseract');
        $directory = dirname($imagePath);
        $filename = basename($imagePath);
        $dockerArgs = $this->tesseractArguments('/data/'.$filename, $language, $psm, $whitelist);

        $result = Process::timeout($timeout)->run(array_merge([
            'docker', 'run', '--rm', '--network', 'none',
            '-v', $directory.':/data:ro',
            $image,
        ], $dockerArgs));

        if ($result->failed()) {
            return '';
        }

        return $result->output();
    }

    /**
     * @return list<string>
     */
    private function tesseractArguments(string $imagePath, string $language, string $psm, ?string $whitelist): array
    {
        $arguments = [
            $imagePath,
            'stdout',
            '-l',
            $language,
            '--psm',
            $psm,
            '--dpi',
            '300',
        ];

        if ($whitelist) {
            $arguments[] = '-c';
            $arguments[] = 'tessedit_char_whitelist='.$whitelist;
        }

        return $arguments;
    }

    private function binaryAvailable(string $binary): bool
    {
        if ($binary === '' || is_executable($binary)) {
            return $binary !== '' && is_executable($binary);
        }

        $lookup = Process::timeout(5)->run(['which', $binary]);

        return $lookup->successful() && trim($lookup->output()) !== '';
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (glob($directory.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($directory);
    }
}
