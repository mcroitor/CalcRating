<?php

namespace App\Models;

use App\Enums\PairingResult;
use App\Models\Concerns\HasOptimisticLocking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pairing extends Model
{
    use HasFactory, HasOptimisticLocking;

    protected $fillable = [
        'tournament_id',
        'round_number',
        'board_number',
        'white_player_id',
        'black_player_id',
        'result',
        'advanced_player_id',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'round_number' => 'integer',
            'board_number' => 'integer',
            'result' => PairingResult::class,
            'version' => 'integer',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function whitePlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'white_player_id');
    }

    public function blackPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'black_player_id');
    }

    public function advancedPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'advanced_player_id');
    }

    public function isBye(): bool
    {
        return $this->black_player_id === null;
    }

    /**
     * Return the player who advanced or won the game, if any.
     */
    public function winnerPlayerId(): ?int
    {
        return match ($this->result) {
            PairingResult::WhiteWin => $this->white_player_id,
            PairingResult::BlackWin => $this->black_player_id,
            PairingResult::Draw, PairingResult::DoubleForfeit => $this->advanced_player_id,
            default => null,
        };
    }

    /**
     * Draws and double forfeits require the arbiter to choose who advances.
     */
    public function requiresAdvancedPlayer(): bool
    {
        return in_array($this->result, [PairingResult::Draw, PairingResult::DoubleForfeit], true);
    }

    public function loserPlayerId(): ?int
    {
        $winner = $this->winnerPlayerId();

        if ($winner === null || $this->black_player_id === null) {
            return null;
        }

        return $winner === $this->white_player_id
            ? $this->black_player_id
            : $this->white_player_id;
    }
}
