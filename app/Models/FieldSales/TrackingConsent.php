<?php

namespace App\Models\FieldSales;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'consent_version', 'accepted_at', 'ip_address', 'user_agent', 'revoked_at'])]
class TrackingConsent extends Model
{
    protected $table = 'fs_tracking_consents';

    protected function casts(): array
    {
        return [
            'consent_version' => 'integer',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
