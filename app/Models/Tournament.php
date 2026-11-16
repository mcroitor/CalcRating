<?php

namespace App\Models;

use App\Enums\PairingType;
use App\Enums\RatingMode;
use App\Enums\TournamentStatus;
use App\Models\Concerns\HasOptimisticLocking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{
    use HasFactory, HasOptimisticLocking;

    protected $fillable = [
        'club_id',
        'arbiter_id',
        'name',
        'pairing_type',
        'rating_mode',
        'rounds_count',
        'games_per_round',
        'start_date',
        'end_date',
        'location',
        'notes',
        'status',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'pairing_type' => PairingType::class,
            'rating_mode' => RatingMode::class,
            'status' => TournamentStatus::class,
            'rounds_count' => 'integer',
            'games_per_round' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'version' => 'integer',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function arbiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'arbiter_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TournamentParticipant::class)->orderBy('id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function pairings(): HasMany
    {
        return $this->hasMany(Pairing::class)->orderBy('round_number')->orderBy('board_number');
    }

    public function isDraft(): bool
    {
        return $this->status === TournamentStatus::Draft;
    }

    public function isInProgress(): bool
    {
        return $this->status === TournamentStatus::InProgress;
    }

    public function isCompleted(): bool
    {
        return $this->status === TournamentStatus::Completed;
    }
}
