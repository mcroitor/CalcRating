<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ClubStaffRole;
use App\Enums\ClubStatus;
use App\Enums\UserRole;
use App\Models\Concerns\HasOptimisticLocking;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasOptimisticLocking, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'surname',
        'email',
        'password',
        'role',
        'version',
        'is_blocked',
        'blocked_at',
        'blocked_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'role' => UserRole::class,
            'version' => 'integer',
            'is_blocked' => 'boolean',
            'blocked_at' => 'datetime',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_blocked', false);
    }

    public function scopeBlocked($query)
    {
        return $query->where('is_blocked', true);
    }

    public function isBlocked(): bool
    {
        return (bool) $this->is_blocked;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isArbiter(): bool
    {
        return $this->role === UserRole::Arbiter;
    }

    public function isGuest(): bool
    {
        return $this->role === UserRole::Guest;
    }

    public function hasRole(UserRole|string|array $roles): bool
    {
        if (is_array($roles)) {
            foreach ($roles as $role) {
                if ($this->hasRole($role)) {
                    return true;
                }
            }

            return false;
        }

        $roleValue = $roles instanceof UserRole ? $roles->value : $roles;
        $currentRoleValue = $this->role instanceof UserRole ? $this->role->value : (string) $this->role;

        return $currentRoleValue === $roleValue;
    }

    public function clubs(): BelongsToMany
    {
        return $this->belongsToMany(Club::class, 'club_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function managedClubs(): BelongsToMany
    {
        return $this->clubs()->wherePivot('role', ClubStaffRole::Manager->value);
    }

    public function isClubManagerOf(Club|int $club): bool
    {
        $clubId = $club instanceof Club ? $club->id : $club;

        return $this->managedClubs()->where('clubs.id', $clubId)->exists();
    }

    public function isClubStaffOf(Club|int $club): bool
    {
        $clubId = $club instanceof Club ? $club->id : $club;

        return $this->clubs()->where('clubs.id', $clubId)->exists();
    }

    public function hasAnyManagedActiveClub(): bool
    {
        return $this->managedClubs()
            ->where('clubs.status', ClubStatus::Active->value)
            ->exists();
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify((new ResetPassword($token))->locale(app()->getLocale()));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify((new VerifyEmail)->locale(app()->getLocale()));
    }
}
