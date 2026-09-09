<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'scoped_rd_codes', 'reports_to_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public function reportsTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reports_to_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'reports_to_id');
    }

    /** The ASM this user (a TSO) reports to — explicit link, else RD-code overlap. */
    public function resolveAsm(): ?self
    {
        if ($this->reportsTo && $this->reportsTo->hasRole('ASM')) {
            return $this->reportsTo;
        }

        $codes = $this->scopedRdCodes();
        if ($codes === []) {
            return null;
        }

        return static::role('ASM')->get()
            ->first(fn (self $asm) => array_intersect($asm->scopedRdCodes(), $codes) !== []);
    }

    /** The NSM this user's chain rolls up to — explicit link, else the sole NSM. */
    public function resolveNsm(): ?self
    {
        $asm = $this->hasRole('ASM') ? $this : $this->resolveAsm();
        if ($asm?->reportsTo && $asm->reportsTo->hasRole('NSM')) {
            return $asm->reportsTo;
        }

        $nsms = static::role('NSM')->get();

        return $nsms->count() === 1 ? $nsms->first() : null;
    }

    /**
     * Distributor (RD) codes this user is limited to. Empty / null means the
     * user sees every record (Super Admin, Admin, NSM).
     *
     * @return list<string>
     */
    public function scopedRdCodes(): array
    {
        return array_values(array_filter((array) ($this->scoped_rd_codes ?? []), fn ($v) => trim((string) $v) !== ''));
    }

    public function isScoped(): bool
    {
        return $this->scopedRdCodes() !== [];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'scoped_rd_codes' => 'array',
        ];
    }
}
