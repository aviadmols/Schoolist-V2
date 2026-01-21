<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Log an event to the audit_logs table.
     *
     * @param string $event
     * @param mixed $auditable
     * @param array|null $oldValues
     * @param array|null $newValues
     * @param int|null $classroomId
     * @return AuditLog
     */
    public function log(
        string $event,
        $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $classroomId = null
    ): AuditLog {
        return AuditLog::create([
            'classroom_id' => $classroomId ?: Request::get('current_classroom')?->id,
            'user_id' => auth()->id(),
            'request_id' => Request::header('X-Request-Id'),
            'event' => $event,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable ? $auditable->id : null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
