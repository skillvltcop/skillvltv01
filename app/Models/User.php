<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    public const ROLE_USER = 'user';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SUPER_ADMIN = 'super_admin';

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'role',
        'permissions',
    ];

    /**
     * Check whether a named administrative permission has been explicitly granted.
     *
     * Super administrators are allowed by the central policy to bypass named
     * permission checks, but not domain invariants or data-integrity constraints.
     */
    public function hasPermissionTo(string $permission): bool
    {
        if ($this->role === self::ROLE_SUPER_ADMIN) {
            return true;
        }

        if ($this->role !== self::ROLE_ADMIN) {
            return false;
        }

        $permissions = $this->permissions ?? [];

        return is_array($permissions) && in_array($permission, $permissions, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
        ];
    }
}
