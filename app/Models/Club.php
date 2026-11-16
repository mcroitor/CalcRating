<?php

namespace App\Models;

use App\Enums\ClubStaffRole;
use App\Enums\ClubStatus;
use App\Models\Concerns\HasOptimisticLocking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Club extends Model
{
    use HasFactory, HasOptimisticLocking;

    protected $fillable = [
        'name',
        'code',
        'city',
        'address',
        'website',
        'description',
        'logo_path',
        'status',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'status' => ClubStatus::class,
            'version' => 'integer',
        ];
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'club_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function managers(): BelongsToMany
    {
        return $this->staff()->wherePivot('role', ClubStaffRole::Manager->value);
    }

    public function arbiters(): BelongsToMany
    {
        return $this->staff()->wherePivot('role', ClubStaffRole::Arbiter->value);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'club_player')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class)->orderByDesc('start_date')->orderByDesc('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ClubStatus::Active->value);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', ClubStatus::Archived->value);
    }

    public function isActive(): bool
    {
        return $this->status === ClubStatus::Active;
    }

    public function isArchived(): bool
    {
        return $this->status === ClubStatus::Archived;
    }

    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    public function initials(): string
    {
        if ($this->code) {
            return Str::upper(Str::substr($this->code, 0, 3));
        }

        $words = preg_split('/\s+/u', trim((string) $this->name)) ?: [];
        $letters = collect($words)
            ->filter(fn (string $word) => $word !== '')
            ->take(2)
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return $letters !== '' ? $letters : 'CL';
    }

    public function hasStaffUser(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $this->staff()->where('users.id', $userId)->exists();
    }

    public function hasManagerUser(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $this->managers()->where('users.id', $userId)->exists();
    }
}
