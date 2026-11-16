<?php

namespace App\Http\Controllers;

use App\Actions\CompleteTournamentAction;
use App\Enums\ClubStatus;
use App\Enums\UserRole;
use App\Exceptions\DomainException;
use App\Http\Requests\StoreTournamentRequest;
use App\Http\Requests\UpdateTournamentRequest;
use App\Models\Club;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\User;
use App\Services\PairingService;
use App\Services\StandingsService;
use App\Services\TournamentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TournamentController extends Controller
{
    public function __construct(
        protected TournamentService $tournamentService,
        protected StandingsService $standings,
        protected PairingService $pairingService,
        protected CompleteTournamentAction $completeTournamentAction
    ) {}

    /**
     * Display a listing of tournaments.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Tournament::class);

        $tournaments = $this->tournamentService->list($request->all());
        $clubs = Club::query()->orderBy('name')->get();

        return view('tournaments.index', compact('tournaments', 'clubs'));
    }

    /**
     * Show the form for creating a new tournament.
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', Tournament::class);

        $user = $request->user();
        $clubs = $user->isAdmin()
            ? Club::query()->active()->with(['staff' => fn ($q) => $q->active()->orderBy('surname')->orderBy('name')])->orderBy('name')->get()
            : $user->managedClubs()->where('clubs.status', ClubStatus::Active->value)->with(['staff' => fn ($q) => $q->active()->orderBy('surname')->orderBy('name')])->orderBy('clubs.name')->get();

        $requestedClubId = $request->query('club_id');
        $selectedClubId = is_numeric($requestedClubId) && $clubs->contains('id', (int) $requestedClubId)
            ? (int) $requestedClubId
            : $clubs->first()?->id;

        $clubArbitersMap = [];
        $admins = $user->isAdmin()
            ? User::query()->active()->where('role', UserRole::Admin->value)->orderBy('surname')->orderBy('name')->get()
            : collect();

        foreach ($clubs as $club) {
            $staff = $club->staff->merge($admins)->unique('id')->sortBy(['surname', 'name'])->values();
            $clubArbitersMap[$club->id] = $staff->map(fn (User $u) => [
                'id' => $u->id,
                'label' => trim("{$u->surname} {$u->name}")." ({$u->email})",
            ])->all();
        }

        return view('tournaments.create', [
            'clubs' => $clubs,
            'selectedClubId' => $selectedClubId,
            'clubArbitersMap' => $clubArbitersMap,
            'arbiters' => $selectedClubId !== null
                ? collect($clubArbitersMap[$selectedClubId] ?? [])
                : collect(),
        ]);
    }

    /**
     * Store a newly created tournament.
     */
    public function store(StoreTournamentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $tournament = $this->tournamentService->create($data, $request->user());

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', __('tournaments.flash.created'));
    }

    /**
     * Display the tournament card.
     */
    public function show(Request $request, Tournament $tournament): View
    {
        Gate::authorize('view', $tournament);

        $tournament->load([
            'club',
            'arbiter',
            'participants.player',
            'pairings.whitePlayer',
            'pairings.blackPlayer',
            'pairings.advancedPlayer',
        ]);

        $canManage = Gate::allows('manageParticipants', $tournament);
        $availablePlayers = collect();

        if ($canManage) {
            $search = trim((string) $request->query('player_search', ''));
            $clubMembersOnly = $request->boolean('club_members_only');
            $registeredPlayerIds = $tournament->participants->pluck('player_id');

            $availablePlayers = Player::query()
                ->whereNotIn('id', $registeredPlayerIds)
                ->when($clubMembersOnly && $tournament->club_id, function ($query) use ($tournament) {
                    $query->whereHas('clubs', fn ($q) => $q->where('clubs.id', $tournament->club_id));
                })
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($subQuery) use ($search) {
                        $subQuery->where('last_name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%");
                    });
                })
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->limit(10)
                ->get();
        }

        $standings = $this->standings->build($tournament);

        $pairingsByRound = $tournament->pairings->groupBy('round_number');
        $canManagePairings = Gate::allows('managePairings', $tournament);
        $canGenerateNextRound = $canManagePairings && $this->pairingService->canGenerateNextRound($tournament);
        $generationBlockReason = $canManagePairings
            ? $this->pairingService->generationBlockReason($tournament)
            : null;
        $nextRoundNumber = $this->pairingService->nextRoundNumber($tournament);

        $canComplete = Gate::allows('complete', $tournament);
        $completionBlockReason = $canComplete
            ? $this->completeTournamentAction->completionBlockReason($tournament)
            : null;
        $overlappingParticipants = $canComplete
            ? $this->completeTournamentAction->overlappingParticipants($tournament)
            : collect();

        return view('tournaments.show', compact(
            'tournament',
            'standings',
            'canManage',
            'availablePlayers',
            'pairingsByRound',
            'canManagePairings',
            'canGenerateNextRound',
            'generationBlockReason',
            'nextRoundNumber',
            'canComplete',
            'completionBlockReason',
            'overlappingParticipants'
        ));
    }

    /**
     * Show the form for editing the specified tournament.
     */
    public function edit(Request $request, Tournament $tournament): View
    {
        Gate::authorize('update', $tournament);

        $tournament->load('club');

        return view('tournaments.edit', [
            'tournament' => $tournament,
            'arbiters' => $this->arbiterOptions($tournament, $request->user()),
        ]);
    }

    /**
     * Update the specified tournament.
     */
    public function update(UpdateTournamentRequest $request, Tournament $tournament): RedirectResponse
    {
        Gate::authorize('update', $tournament);

        $data = $request->safe()->except(['version', 'club_id']);
        $user = $request->user();

        if (! $user->isAdmin() && ! ($tournament->club?->hasManagerUser($user) ?? false)) {
            unset($data['arbiter_id']);
        }

        $this->tournamentService->update(
            $tournament,
            $data,
            (int) $request->input('version')
        );

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', __('tournaments.flash.updated'));
    }

    /**
     * Remove the specified tournament.
     */
    public function destroy(Tournament $tournament): RedirectResponse
    {
        Gate::authorize('delete', $tournament);

        $name = $tournament->name;
        $this->tournamentService->delete($tournament);

        return redirect()
            ->route('tournaments.index')
            ->with('success', __('tournaments.flash.deleted', ['name' => $name]));
    }

    /**
     * Start the tournament and freeze starting ratings.
     */
    public function start(Request $request, Tournament $tournament): RedirectResponse
    {
        Gate::authorize('start', $tournament);

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->tournamentService->start($tournament, (int) $validated['version']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', __('tournaments.flash.started'));
    }

    /**
     * Complete the tournament and persist the Elo calculation.
     */
    public function complete(Request $request, Tournament $tournament): RedirectResponse
    {
        Gate::authorize('complete', $tournament);

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'confirm_overlap' => ['nullable', 'boolean'],
        ]);

        try {
            $this->completeTournamentAction->execute(
                $tournament,
                (int) $validated['version'],
                $request->user(),
                (bool) ($validated['confirm_overlap'] ?? false)
            );
        } catch (DomainException $e) {
            return redirect()
                ->route('tournaments.show', $tournament)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', __('tournaments.flash.completed'));
    }

    /**
     * @return Collection<int, User>
     */
    private function arbiterOptions(?Tournament $tournament = null, ?User $actor = null): Collection
    {
        if ($tournament?->club) {
            $staff = $tournament->club->staff()->active()->orderBy('surname')->orderBy('name')->get();
            if ($actor?->isAdmin()) {
                $admins = User::query()->active()->where('role', UserRole::Admin->value)->orderBy('surname')->orderBy('name')->get();

                return $staff->merge($admins)->unique('id')->sortBy(['surname', 'name'])->values();
            }

            return $staff;
        }

        return User::query()
            ->active()
            ->whereIn('role', ['arbiter', 'admin'])
            ->orderBy('surname')
            ->orderBy('name')
            ->get();
    }
}
