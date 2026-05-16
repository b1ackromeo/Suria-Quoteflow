<?php

namespace App\Support;

use App\Models\AuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Audit
{
    public static function record(string $action, ?Model $model = null, ?array $before = null, ?array $after = null): void
    {
        AuditTrail::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $model ? $model::class : null,
            'auditable_id' => $model?->getKey(),
            'before_values' => $before,
            'after_values' => $after,
            'ip_address' => request()?->ip(),
        ]);
    }
}
