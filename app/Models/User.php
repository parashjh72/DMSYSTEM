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

#[Fillable(['name', 'email', 'password', 'scoped_tsos'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * TSO names this user is limited to (from the territory_officers master).
     * Empty / null means no restriction.
     *
     * @return list<string>
     */
    public function scopedTsos(): array
    {
        return array_values(array_filter((array) ($this->scoped_tsos ?? []), fn ($v) => trim((string) $v) !== ''));
    }

    public function isTsoScoped(): bool
    {
        return $this->scopedTsos() !== [];
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
            'scoped_tsos' => 'array',
        ];
    }
}
