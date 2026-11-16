<?php

namespace App\Services;

use App\Enums\ClubStaffRole;
use App\Enums\ClubStatus;
use App\Exceptions\DomainException;
use App\Models\Club;
use App\Models\Player;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClubService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function filteredQuery(array $filters = []): Builder
    {
        $query = Club::query()
            ->withCount(['members', 'tournaments', 'staff']);

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        $statusFilter = (string) ($filters['status'] ?? ClubStatus::Active->value);
        if ($statusFilter !== 'all') {
            $status = ClubStatus::tryFrom($statusFilter);
            if ($status !== null) {
                $query->where('status', $status->value);
            }
        }

        return $query->orderBy('name');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $logo, User $actor): Club
    {
        $manager = User::query()->findOrFail((int) $data['manager_id']);
        $this->ensureEligibleForStaff($manager);

        $logoPath = $logo ? $logo->store('clubs', 'public') : null;

        try {
            /** @var Club $club */
            $club = DB::transaction(function () use ($data, $logoPath, $manager): Club {
                $club = Club::create([
                    'name' => trim((string) $data['name']),
                    'code' => ! empty($data['code']) ? strtoupper(trim((string) $data['code'])) : null,
                    'city' => ! empty($data['city']) ? trim((string) $data['city']) : null,
                    'address' => ! empty($data['address']) ? trim((string) $data['address']) : null,
                    'website' => ! empty($data['website']) ? trim((string) $data['website']) : null,
                    'description' => ! empty($data['description']) ? trim((string) $data['description']) : null,
                    'logo_path' => $logoPath,
                    'status' => ClubStatus::Active,
                    'version' => 1,
                ]);

                $club->staff()->attach($manager->id, [
                    'role' => ClubStaffRole::Manager->value,
                ]);

                return $club;
            });
        } catch (\Throwable $e) {
            if ($logoPath !== null) {
                Storage::disk('public')->delete($logoPath);
            }

            throw $e;
        }

        $this->audit->log('club.created', $actor, 'club', $club->id, [
            'name' => $club->name,
            'manager_id' => $manager->id,
        ]);

        return $club;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(
        Club $club,
        array $data,
        int $expectedVersion,
        ?UploadedFile $logo,
        bool $removeLogo,
        User $actor
    ): Club {
        $oldLogoPath = $club->logo_path;
        $newLogoPath = $logo ? $logo->store('clubs', 'public') : null;

        try {
            $updated = DB::transaction(function () use ($club, $data, $expectedVersion, $newLogoPath, $removeLogo, $oldLogoPath): Club {
                $payload = [
                    'name' => trim((string) $data['name']),
                    'code' => ! empty($data['code']) ? strtoupper(trim((string) $data['code'])) : null,
                    'city' => ! empty($data['city']) ? trim((string) $data['city']) : null,
                    'address' => ! empty($data['address']) ? trim((string) $data['address']) : null,
                    'website' => ! empty($data['website']) ? trim((string) $data['website']) : null,
                    'description' => ! empty($data['description']) ? trim((string) $data['description']) : null,
                ];

                if ($newLogoPath !== null) {
                    $payload['logo_path'] = $newLogoPath;
                } elseif ($removeLogo) {
                    $payload['logo_path'] = null;
                } else {
                    $payload['logo_path'] = $oldLogoPath;
                }

                $club->updateWithVersion($payload, $expectedVersion);

                return $club->fresh();
            });
        } catch (\Throwable $e) {
            if ($newLogoPath !== null) {
                Storage::disk('public')->delete($newLogoPath);
            }

            throw $e;
        }

        if ($oldLogoPath !== null && ($newLogoPath !== null || $removeLogo)) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        $this->audit->log('club.updated', $actor, 'club', $updated->id, [
            'name' => $updated->name,
        ]);

        return $updated;
    }

    public function archive(Club $club, int $expectedVersion, User $actor): Club
    {
        if ($club->isArchived()) {
            return $club;
        }

        $updated = DB::transaction(function () use ($club, $expectedVersion): Club {
            $club->updateWithVersion([
                'status' => ClubStatus::Archived->value,
            ], $expectedVersion);

            return $club->fresh();
        });

        $this->audit->log('club.archived', $actor, 'club', $updated->id);

        return $updated;
    }

    public function restore(Club $club, int $expectedVersion, User $actor): Club
    {
        if ($club->isActive()) {
            return $club;
        }

        $updated = DB::transaction(function () use ($club, $expectedVersion): Club {
            $club->updateWithVersion([
                'status' => ClubStatus::Active->value,
            ], $expectedVersion);

            return $club->fresh();
        });

        $this->audit->log('club.restored', $actor, 'club', $updated->id);

        return $updated;
    }

    public function delete(Club $club, User $actor): void
    {
        if ($club->tournaments()->exists()) {
            throw new DomainException(__('clubs.errors.delete_has_tournaments'));
        }

        $logoPath = $club->logo_path;
        $clubId = $club->id;
        $clubName = $club->name;

        DB::transaction(function () use ($club): void {
            if ($club->tournaments()->exists()) {
                throw new DomainException(__('clubs.errors.delete_has_tournaments'));
            }

            $club->delete();
        });

        if ($logoPath !== null) {
            Storage::disk('public')->delete($logoPath);
        }

        $this->audit->log('club.deleted', $actor, 'club', $clubId, [
            'name' => $clubName,
        ]);
    }

    public function assignStaff(Club $club, User $targetUser, ClubStaffRole $role, User $actor): void
    {
        if (! $club->isActive()) {
            throw new DomainException(__('clubs.errors.archived_immutable'));
        }

        $this->ensureEligibleForStaff($targetUser);

        DB::transaction(function () use ($club, $targetUser, $role): void {
            $currentPivot = $club->staff()->where('users.id', $targetUser->id)->first();

            if ($currentPivot !== null
                && $currentPivot->pivot->role === ClubStaffRole::Manager->value
                && $role !== ClubStaffRole::Manager
            ) {
                $this->ensureAnotherActiveManagerExists($club, $targetUser);
            }

            $club->staff()->syncWithoutDetaching([
                $targetUser->id => ['role' => $role->value],
            ]);
            $club->staff()->updateExistingPivot($targetUser->id, ['role' => $role->value]);
        });

        $this->audit->log('club.staff_changed', $actor, 'club', $club->id, [
            'action' => 'assigned',
            'user_id' => $targetUser->id,
            'role' => $role->value,
        ]);
    }

    public function removeStaff(Club $club, User $targetUser, User $actor): void
    {
        if (! $club->isActive()) {
            throw new DomainException(__('clubs.errors.archived_immutable'));
        }

        DB::transaction(function () use ($club, $targetUser): void {
            $currentPivot = $club->staff()->where('users.id', $targetUser->id)->first();

            if ($currentPivot !== null && $currentPivot->pivot->role === ClubStaffRole::Manager->value) {
                $this->ensureAnotherActiveManagerExists($club, $targetUser);
            }

            $club->staff()->detach($targetUser->id);
        });

        $this->audit->log('club.staff_changed', $actor, 'club', $club->id, [
            'action' => 'removed',
            'user_id' => $targetUser->id,
        ]);
    }

    public function addMember(Club $club, Player $player, User $actor): void
    {
        if (! $club->isActive()) {
            throw new DomainException(__('clubs.errors.archived_immutable'));
        }

        DB::transaction(function () use ($club, $player): void {
            $club->members()->syncWithoutDetaching([
                $player->id => ['joined_at' => now()->toDateString()],
            ]);
        });

        $this->audit->log('club.member_added', $actor, 'club', $club->id, [
            'player_id' => $player->id,
        ]);
    }

    public function removeMember(Club $club, Player $player, User $actor): void
    {
        if (! $club->isActive()) {
            throw new DomainException(__('clubs.errors.archived_immutable'));
        }

        DB::transaction(function () use ($club, $player): void {
            $club->members()->detach($player->id);
        });

        $this->audit->log('club.member_removed', $actor, 'club', $club->id, [
            'player_id' => $player->id,
        ]);
    }

    private function ensureEligibleForStaff(User $user): void
    {
        if ($user->isBlocked() || ! $user->hasRole(['arbiter', 'admin'])) {
            throw new DomainException(__('clubs.errors.ineligible_staff'));
        }
    }

    private function ensureAnotherActiveManagerExists(Club $club, User $excludingUser): void
    {
        $otherActiveManagers = $club->managers()
            ->where('users.id', '!=', $excludingUser->id)
            ->where('users.is_blocked', false)
            ->count();

        if ($otherActiveManagers < 1) {
            throw new DomainException(__('clubs.errors.last_manager'));
        }
    }
}
