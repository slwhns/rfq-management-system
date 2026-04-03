<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_SUPERADMIN = 'superadmin';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_STAFF = 'staff';

    public const ROLE_MAP = [
        'client' => self::ROLE_STAFF,
        'qs' => self::ROLE_STAFF,
        self::ROLE_SUPERADMIN => self::ROLE_SUPERADMIN,
        self::ROLE_ADMIN => self::ROLE_ADMIN,
        self::ROLE_STAFF => self::ROLE_STAFF,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'company_name',
        'role',
        'phone_number',
        'department',
        'profile_photo_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function normalizedRole(): string
    {
        return self::ROLE_MAP[$this->role] ?? self::ROLE_STAFF;
    }

    public function hasRole(string $role): bool
    {
        return $this->normalizedRole() === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->normalizedRole(), $roles, true);
    }
}
