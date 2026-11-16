<?php

namespace App\Models;

use App\Enums\ChessTitle;
use App\Enums\TournamentStatus;
use App\Models\Concerns\HasOptimisticLocking;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    use HasFactory, HasOptimisticLocking;

    protected $fillable = [
        'first_name',
        'last_name',
        'birth_date',
        'title',
        'current_rating',
        'notes',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'title' => ChessTitle::class,
            'current_rating' => 'integer',
            'version' => 'integer',
        ];
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class)->orderBy('created_at', 'asc')->orderBy('id', 'asc');
    }

    public function tournamentParticipations(): HasMany
    {
        return $this->hasMany(TournamentParticipant::class);
    }

    public function clubs(): BelongsToMany
    {
        return $this->belongsToMany(Club::class, 'club_player')
            ->withPivot('joined_at')
            ->withTimestamps()
            ->orderBy('clubs.name');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->last_name} {$this->first_name}";
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date ? Carbon::parse($this->birth_date)->age : null;
    }

    /**
     * Check if player has tournament rating history.
     */
    public function hasTournamentRatingHistory(): bool
    {
        $ratingsCount = $this->ratings()->count();

        if ($ratingsCount > 1) {
            return true;
        }

        if ($ratingsCount === 1) {
            return $this->ratings()->whereNotNull('tournament_id')->exists();
        }

        return false;
    }

    /**
     * Check if the player takes part in an in-progress or completed tournament.
     */
    public function hasActiveTournamentParticipation(): bool
    {
        return $this->tournamentParticipations()
            ->whereHas('tournament', function ($query) {
                $query->whereIn('status', [
                    TournamentStatus::InProgress->value,
                    TournamentStatus::Completed->value,
                ]);
            })
            ->exists();
    }
}
