<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'role', 'theme', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLES = ['administrator', 'pod', 'coordinator', 'scanner'];

    /**
     * Color palettes a user can pick for their interface.
     */
    public const THEMES = ['evergreen', 'ocean', 'royal', 'rose', 'crimson', 'sunset', 'graphite'];

    /**
     * Human-readable role labels, keyed by role value.
     */
    public const ROLE_LABELS = [
        'administrator' => 'Administrator',
        'pod' => 'Prefect of Discipline (POD)',
        'coordinator' => 'Coordinator',
        'scanner' => 'Scanner',
    ];

    public function isAdmin(): bool
    {
        return $this->role === 'administrator';
    }

    public static function roleLabel(string $role): string
    {
        return self::ROLE_LABELS[$role] ?? ucfirst($role);
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
        ];
    }
}
