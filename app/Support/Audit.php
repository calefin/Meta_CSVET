<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Thin wrapper around the audit_logs table (spec sec. 10.4/12): records who
 * did what, when, and from which IP, for critical/sensitive actions.
 */
class Audit
{
    public static function record(string $action, ?Model $model = null, ?int $userId = null): void
    {
        AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'model' => $model ? get_class($model) : null,
            'model_id' => $model?->getKey(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
