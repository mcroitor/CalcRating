<?php

namespace App\Services;

use App\Enums\PairingType;
use App\Enums\RatingMode;
use App\Enums\TournamentStatus;
use App\Exceptions\DomainException;
use App\Models\Player;
use App\Models\Rating;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TournamentService
{
    /**
     * Build filtered query for tournaments.
     *
     * @param  array<string, mixed>  $filters
     */
    public function filteredQuery(array $filters = []): Builder
    {
        $query = Tournament::query()->with(['club', 'arbiter'])->withCount('participants');

        if (! empty($filters['club_id']) && is_numeric($filters['club_id'])) {
            $query->where('club_id', (int) $filters['club_id']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where('name', 'like', "%{$search}%");
        }

        if (! empty($filters['status'])) {
            $status = TournamentStatus::tryFrom((string) $filters['status']);

            if ($status) {
                $query->where('status', $status->value);
            }
        }

        if (! empty($filters['pairing_type'])) {
            $pairingType = PairingType::tryFrom((string) $filters['pairing_type']);

            if ($pairingType) {
                $query->where('pairing_type', $pairingType->value);
            }
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('start_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('start_date', '<=', $filters['date_to']);
        }

        return $query
            ->orderByDesc('start_date')
            ->orderByDesc('id');
    }

    /**
     * Get paginated tournaments list.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)->paginate($perPage)->withQueryString();
    }

    /**
     * Create a tournament. Arbiters always own their tournaments; admins may assign another arbiter.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Tournament
    {
        return DB::transaction(function () use ($data, $actor) {
            $data['club_id'] = (int) $data['club_id'];
            $data['arbiter_id'] = ! empty($data['arbiter_id'])
                ? (int) $data['arbiter_id']
                : $actor->id;

            $data['rating_mode'] = $data['rating_mode'] ?? RatingMode::Average->value;
            $data['status'] = TournamentStatus::Draft->value;
            $data['version'] = 1;

            return Tournament::create($data);
        });
    }

    /**
     * Update a tournament using optimistic locking.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws DomainException
     */
    public function update(Tournament $tournament, array $data, int $expectedVersion): Tournament
    {
        return DB::transaction(function () use ($tournament, $data, $expectedVersion) {
            $fresh = Tournament::query()->findOrFail($tournament->getKey());

            $data = Arr::only($data, [
                'name',
                'pairing_type',
                'rating_mode',
                'rounds_count',
                'games_per_round',
                'start_date',
                'end_date',
                'location',
                'notes',
                'arbiter_id',
            ]);

            if (array_key_exists('rating_mode', $data) && $data['rating_mode'] === null) {
                unset($data['rating_mode']);
            }

            $this->assertRegistrationParametersUnchanged($fresh, $data);

            $fresh->updateWithVersion($data, $expectedVersion);

            return $fresh;
        });
    }

    /**
     * Start a draft tournament: freeze the roster and capture actual ratings.
     *
     * @throws DomainException
     */
    public function start(Tournament $tournament, int $expectedVersion): Tournament
    {
        return DB::transaction(function () use ($tournament, $expectedVersion) {
            $fresh = Tournament::query()->findOrFail($tournament->getKey());

            if (! $fresh->isDraft()) {
                throw new DomainException(__('tournaments.errors.start_only_draft'));
            }

            $participants = $fresh->participants()->with('player')->get();

            if ($participants->count() < 2) {
                throw new DomainException(__('tournaments.errors.start_min_participants'));
            }

            $fresh->updateWithVersion(
                ['status' => TournamentStatus::InProgress->value],
                $expectedVersion
            );

            foreach ($participants as $participant) {
                $participant->updateWithVersion(
                    ['rating_before' => $participant->player->current_rating],
                    $participant->version
                );
            }

            Log::info('Tournament started, starting ratings frozen', [
                'tournament_id' => $fresh->id,
                'participants' => $participants->count(),
            ]);

            return $fresh;
        });
    }

    /**
     * Add a player to a draft tournament and bump the tournament version.
     *
     * @throws DomainException
     */
    public function addParticipant(Tournament $tournament, int $playerId, int $expectedVersion): TournamentParticipant
    {
        return DB::transaction(function () use ($tournament, $playerId, $expectedVersion) {
            $fresh = Tournament::query()->findOrFail($tournament->getKey());

            if (! $fresh->isDraft()) {
                throw new DomainException(__('tournaments.errors.roster_only_draft'));
            }

            $player = Player::query()->findOrFail($playerId);

            if ($fresh->participants()->where('player_id', $player->id)->exists()) {
                throw new DomainException(__('tournaments.errors.already_participant'));
            }

            $fresh->updateWithVersion([], $expectedVersion);

            try {
                return $fresh->participants()->create([
                    'player_id' => $player->id,
                    'points' => 0,
                    'rating_before' => $player->current_rating,
                    'version' => 1,
                ]);
            } catch (QueryException $e) {
                if ($this->isUniqueViolation($e)) {
                    throw new DomainException(__('tournaments.errors.already_participant'));
                }

                throw $e;
            }
        });
    }

    /**
     * Remove a player from a draft tournament and bump the tournament version.
     *
     * @throws DomainException
     */
    public function removeParticipant(
        Tournament $tournament,
        TournamentParticipant $participant,
        int $expectedVersion
    ): void {
        DB::transaction(function () use ($tournament, $participant, $expectedVersion) {
            $fresh = Tournament::query()->findOrFail($tournament->getKey());

            if (! $fresh->isDraft()) {
                throw new DomainException(__('tournaments.errors.roster_only_draft'));
            }

            $participant = TournamentParticipant::query()
                ->where('tournament_id', $fresh->id)
                ->findOrFail($participant->getKey());

            $fresh->updateWithVersion([], $expectedVersion);

            $participant->delete();
        });
    }

    /**
     * Delete a tournament. Rating history is preserved and detached from it.
     */
    public function delete(Tournament $tournament): void
    {
        DB::transaction(function () use ($tournament) {
            Rating::query()
                ->where('tournament_id', $tournament->id)
                ->update(['tournament_id' => null]);

            $tournament->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws DomainException
     */
    private function assertRegistrationParametersUnchanged(Tournament $tournament, array $data): void
    {
        if ($tournament->isDraft()) {
            return;
        }

        foreach (['pairing_type', 'rating_mode', 'rounds_count', 'games_per_round'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $new = $data[$field] instanceof \BackedEnum ? $data[$field]->value : $data[$field];
            $old = $tournament->{$field} instanceof \BackedEnum
                ? $tournament->{$field}->value
                : $tournament->{$field};

            if ((string) $new !== (string) $old) {
                throw new DomainException(__('tournaments.errors.locked_parameters'));
            }
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return (string) $e->getCode() === '23000'
            || str_contains($e->getMessage(), 'UNIQUE constraint failed');
    }
}
