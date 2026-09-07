<?php

namespace App\Console\Commands;

use App\Models\OcrScan;
use App\Models\Person;
use App\Services\CnicImageStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneExpiredCnicDataCommand extends Command
{
    protected $signature = 'cnic:prune-expired';

    protected $description = 'Delete expired temporary CNIC images, optional person images, and debug OCR text.';

    public function handle(CnicImageStorage $images): int
    {
        $disk = Storage::disk($images->disk());
        $tempHours = (int) config('cnic.temp_image_retention_hours', 24);
        $cutoff = now()->subHours($tempHours)->getTimestamp();

        foreach ($disk->files('tmp') as $path) {
            if ($disk->lastModified($path) <= $cutoff) {
                $disk->delete($path);
            }
        }

        OcrScan::query()
            ->whereNotNull('raw_text_encrypted')
            ->where(function ($query) {
                $query->whereNull('raw_expires_at')
                    ->orWhere('raw_expires_at', '<=', now());
            })
            ->update([
                'raw_text_encrypted' => null,
                'raw_expires_at' => null,
            ]);

        $retentionDays = config('cnic.person_image_retention_days');

        if (is_numeric($retentionDays) && (int) $retentionDays > 0) {
            $personCutoff = now()->subDays((int) $retentionDays);

            Person::query()
                ->where('created_at', '<=', $personCutoff)
                ->where(function ($query) {
                    $query->whereNotNull('front_image_path')->orWhereNotNull('back_image_path');
                })
                ->each(function (Person $person) use ($images) {
                    $images->delete($person->front_image_path);
                    $images->delete($person->back_image_path);
                    $person->update([
                        'front_image_path' => null,
                        'back_image_path' => null,
                    ]);
                });
        }

        $this->info('Expired CNIC data pruned.');

        return self::SUCCESS;
    }
}
