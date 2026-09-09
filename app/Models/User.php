<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'scoped_rd_codes'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

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
