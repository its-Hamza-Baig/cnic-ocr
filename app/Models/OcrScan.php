<?php

namespace App\Models;

use App\Enums\OcrScanStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'person_id',
    'provider',
    'status',
    'confidence',
    'processing_time_ms',
    'raw_text_encrypted',
    'raw_expires_at',
])]
#[Hidden(['raw_text_encrypted'])]
class OcrScan extends Model
{
    protected function casts(): array
    {
        return [
            'status' => OcrScanStatus::class,
            'confidence' => 'float',
            'raw_expires_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
