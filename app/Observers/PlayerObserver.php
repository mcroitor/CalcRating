<?php

namespace App\Observers;

use App\Models\Player;
use App\Models\Rating;

class PlayerObserver
{
    /**
     * Handle the Player "created" event.
     */
    public function created(Player $player): void
    {
        // Automatically create initial rating record
        Rating::create([
            'player_id' => $player->id,
            'tournament_id' => null,
            'value' => $player->current_rating,
            'created_at' => $player->created_at ?? now(),
        ]);
    }
}
