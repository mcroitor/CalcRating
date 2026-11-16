<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Exceptions\DomainException;
use App\Http\Requests\StoreClubRequest;
use App\Http\Requests\UpdateClubRequest;
use App\Models\Club;
use App\Models\Player;
use App\Models\User;
use App\Services\ClubService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ClubController extends Controller
{
    public function __construct(
        private readonly ClubService $clubService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Club::class);

        $clubs = $this->clubService->list($request->all());

        return view('clubs.index', compact('clubs'));
    }

    public function create(): View
    {
        Gate::authorize('create', Club::class);

        $eligibleManagers = User::query()
            ->active()
            ->whereIn('role', [UserRole::Arbiter->value, UserRole::Admin->value])
            ->orderBy('surname')
            ->orderBy('name')
            ->get();

        return view('clubs.create', compact('eligibleManagers'));
    }

    public function store(StoreClubRequest $request): RedirectResponse
    {
        Gate::authorize('create', Club::class);

        $club = $this->clubService->create(
            $request->validated(),
            $request->file('logo'),
            $request->user()
        );

        return redirect()
            ->route('clubs.show', $club)
            ->with('success', __('clubs.flash.created'));
    }

    public function show(Request $request, Club $club): View
    {
        Gate::authorize('view', $club);

        $club->load([
            'staff' => fn ($q) => $q->orderBy('surname')->orderBy('name'),
            'members' => fn ($q) => $q->orderBy('last_name')->orderBy('first_name'),
            'tournaments' => fn ($q) => $q->with('arbiter')->withCount('participants'),
        ]);

        $availableStaffUsers = collect();
        if (Gate::allows('manageArbiters', $club) || Gate::allows('manageManagers', $club)) {
            $existingStaffIds = $club->staff->pluck('id');
            $availableStaffUsers = User::query()
                ->active()
                ->whereIn('role', [UserRole::Arbiter->value, UserRole::Admin->value])
                ->whereNotIn('id', $existingStaffIds)
                ->orderBy('surname')
                ->orderBy('name')
                ->get();
        }

        $availablePlayers = collect();
        if (Gate::allows('manageMembers', $club)) {
            $search = trim((string) $request->query('member_search', ''));
            $memberIds = $club->members->pluck('id');
            $availablePlayers = Player::query()
                ->whereNotIn('id', $memberIds)
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

        return view('clubs.show', compact('club', 'availableStaffUsers', 'availablePlayers'));
    }

    public function edit(Club $club): View
    {
        Gate::authorize('update', $club);

        return view('clubs.edit', compact('club'));
    }

    public function update(UpdateClubRequest $request, Club $club): RedirectResponse
    {
        Gate::authorize('update', $club);

        $validated = $request->validated();

        $this->clubService->update(
            $club,
            $validated,
            (int) $validated['version'],
            $request->file('logo'),
            (bool) ($validated['remove_logo'] ?? false),
            $request->user()
        );

        return redirect()
            ->route('clubs.show', $club)
            ->with('success', __('clubs.flash.updated'));
    }

    public function archive(Request $request, Club $club): RedirectResponse
    {
        Gate::authorize('archive', $club);

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
        ]);

        $this->clubService->archive($club, (int) $validated['version'], $request->user());

        return redirect()
            ->route('clubs.show', $club)
            ->with('success', __('clubs.flash.archived'));
    }

    public function restore(Request $request, Club $club): RedirectResponse
    {
        Gate::authorize('restore', $club);

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
        ]);

        $this->clubService->restore($club, (int) $validated['version'], $request->user());

        return redirect()
            ->route('clubs.show', $club)
            ->with('success', __('clubs.flash.restored'));
    }

    public function destroy(Request $request, Club $club): RedirectResponse
    {
        Gate::authorize('delete', $club);

        try {
            $this->clubService->delete($club, $request->user());
        } catch (DomainException $e) {
            return redirect()
                ->route('clubs.show', $club)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('clubs.index')
            ->with('success', __('clubs.flash.deleted'));
    }
}
