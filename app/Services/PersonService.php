<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\VerificationStatus;
use App\Exceptions\DuplicateCnicException;
use App\Models\OcrScan;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class PersonService
{
    public function __construct(
        private readonly CnicNormalizerService $normalizer,
        private readonly DuplicateCheckService $duplicates,
        private readonly AuditLogService $auditLogs,
        private readonly CnicImageStorage $images,
        private readonly PendingScanSession $pendingScan,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function confirmAndCreate(User $user, array $data): Person
    {
        $pending = $this->pendingScan->get();

        if ($pending === null) {
            throw new \RuntimeException('No pending CNIC scan was found for confirmation.');
        }

        $cnic = $this->normalizer->normalize((string) $data['cnic']);

        if (! $this->normalizer->isValid($cnic)) {
            throw new \InvalidArgumentException('The CNIC format is invalid.');
        }

        try {
            $person = DB::transaction(function () use ($user, $data, $pending, $cnic) {
                $existing = Person::query()
                    ->withTrashed()
                    ->where('cnic', $cnic)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    throw new DuplicateCnicException($existing);
                }

                $person = Person::query()->create([
                    'cnic' => $cnic,
                    'name' => $data['name'],
                    'father_husband_name' => $data['father_husband_name'] ?: null,
                    'address' => $data['address'] ?: null,
                    'verification_status' => VerificationStatus::from($data['verification_status'] ?? VerificationStatus::Pending->value),
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                $frontPath = isset($pending['front_image_path'])
                    ? $this->images->promote((string) $pending['front_image_path'], $person->id, 'front')
                    : null;
                $backPath = ! empty($pending['back_image_path'])
                    ? $this->images->promote((string) $pending['back_image_path'], $person->id, 'back')
                    : null;

                $person->update([
                    'front_image_path' => $frontPath,
                    'back_image_path' => $backPath,
                ]);

                if (! empty($pending['ocr_scan_id'])) {
                    OcrScan::query()
                        ->whereKey($pending['ocr_scan_id'])
                        ->update(['person_id' => $person->id]);
                }

                $this->auditLogs->record($person, AuditAction::Created, $user, ['cnic', 'name', 'father_husband_name', 'address']);

                return $person->refresh();
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw new DuplicateCnicException($this->duplicates->find((string) $cnic));
            }

            throw $exception;
        }

        session()->forget(PendingScanSession::KEY);

        return $person;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Person $person, User $user, array $data): Person
    {
        $cnic = $this->normalizer->normalize((string) $data['cnic']);

        if (! $this->normalizer->isValid($cnic)) {
            throw new \InvalidArgumentException('The CNIC format is invalid.');
        }

        try {
            return DB::transaction(function () use ($person, $user, $data, $cnic) {
                $existing = Person::query()
                    ->withTrashed()
                    ->where('cnic', $cnic)
                    ->where('id', '!=', $person->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    throw new DuplicateCnicException($existing);
                }

                $person->fill([
                    'cnic' => $cnic,
                    'name' => $data['name'],
                    'father_husband_name' => $data['father_husband_name'] ?: null,
                    'address' => $data['address'] ?: null,
                    'verification_status' => VerificationStatus::from($data['verification_status'] ?? $person->verification_status->value),
                    'updated_by' => $user->id,
                ]);

                $changed = array_keys($person->getDirty());
                $person->save();

                if ($changed !== []) {
                    $this->auditLogs->record($person, AuditAction::Updated, $user, $changed);
                }

                return $person->refresh();
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw new DuplicateCnicException($this->duplicates->find((string) $cnic, $person->id));
            }

            throw $exception;
        }
    }

    public function delete(Person $person, User $user): void
    {
        $person->delete();
        $this->auditLogs->record($person, AuditAction::Deleted, $user);
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');

        return $sqlState === '23000' || str_contains($exception->getMessage(), 'UNIQUE') || str_contains($exception->getMessage(), 'Duplicate');
    }
}
