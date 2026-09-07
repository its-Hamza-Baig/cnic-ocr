<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogService
{
    /**
     * @param  list<string>|null  $changedFields
     */
    public function record(
        Model $auditable,
        AuditAction $action,
        ?User $user = null,
        ?array $changedFields = null,
        ?Request $request = null,
    ): AuditLog {
        $request ??= request();

        return AuditLog::query()->create([
            'user_id' => $user?->id,
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->getKey(),
            'action' => $action,
            'changed_fields' => $changedFields,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
