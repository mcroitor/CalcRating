<?php

namespace App\Services;

use App\Exceptions\DomainException;
use App\Models\Player;
use App\Models\Rating;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlayerService
{
    /**
     * Build filtered query for players.
     *
     * @param  array<string, mixed>  $filters
     */
    public function filteredQuery(array $filters = []): Builder
    {
        $query = Player::query()->with('clubs');

        if (! empty($filters['club_id'])) {
            if ($filters['club_id'] === 'none') {
                $query->whereDoesntHave('clubs');
            } elseif (is_numeric($filters['club_id'])) {
                $clubId = (int) $filters['club_id'];
                $query->whereHas('clubs', fn (Builder $q) => $q->where('clubs.id', $clubId));
            }
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('last_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['title'])) {
            $query->where('title', $filters['title']);
        }

        if (isset($filters['min_rating']) && is_numeric($filters['min_rating'])) {
            $query->where('current_rating', '>=', (int) $filters['min_rating']);
        }

        if (isset($filters['max_rating']) && is_numeric($filters['max_rating'])) {
            $query->where('current_rating', '<=', (int) $filters['max_rating']);
        }

        $sortField = $filters['sort_by'] ?? 'last_name';
        $sortDirection = ($filters['sort_dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if (in_array($sortField, ['last_name', 'first_name', 'current_rating', 'birth_date', 'id'])) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('last_name', 'asc');
        }

        return $query;
    }

    /**
     * Get paginated players list with filtering.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)->paginate($perPage)->withQueryString();
    }

    /**
     * Get all filtered players collection (for export).
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Player>
     */
    public function all(array $filters = []): Collection
    {
        return $this->filteredQuery($filters)->get();
    }

    /**
     * Create a new player.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Player
    {
        return DB::transaction(function () use ($data) {
            $data['title'] = $data['title'] ?? 'None';
            $data['version'] = 1;

            return Player::create($data);
        });
    }

    /**
     * Update an existing player with optimistic locking.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Player $player, array $data, int $version): Player
    {
        return DB::transaction(function () use ($player, $data, $version) {
            $player->updateWithVersion($data, $version);

            return $player;
        });
    }

    /**
     * Delete player if allowed by business rules.
     *
     * @throws DomainException
     */
    public function delete(Player $player): void
    {
        DB::transaction(function () use ($player) {
            if ($player->hasTournamentRatingHistory()) {
                throw new DomainException(__('players.errors.delete_has_history'));
            }

            if ($player->hasActiveTournamentParticipation()) {
                throw new DomainException(__('players.errors.delete_active_tournament'));
            }

            // Remove initial ratings (tournament_id is null)
            Rating::where('player_id', $player->id)->delete();

            $player->delete();
        });
    }
}
