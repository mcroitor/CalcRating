<?php

namespace App\Models;

use App\Models\Concerns\HasOptimisticLocking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentParticipant extends Model
{
    use HasFactory, HasOptimisticLocking;

    protected $fillable = [
        'tournament_id',
        'player_id',
        'points',
        'rating_before',
        'rating_after',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'decimal:1',
            'rating_before' => 'integer',
            'rating_after' => 'integer',
            'version' => 'integer',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
