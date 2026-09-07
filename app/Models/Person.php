<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'cnic',
    'name',
    'father_husband_name',
    'address',
    'front_image_path',
    'back_image_path',
    'verification_status',
    'created_by',
    'updated_by',
])]
class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'persons';

    protected function casts(): array
    {
        return [
            'verification_status' => VerificationStatus::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function ocrScans(): HasMany
    {
        return $this->hasMany(OcrScan::class);
    }
}
