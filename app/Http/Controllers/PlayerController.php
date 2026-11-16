<?php

namespace App\Http\Controllers;

use App\Actions\ImportPlayersAction;
use App\Enums\ClubStatus;
use App\Http\Requests\StorePlayerRequest;
use App\Http\Requests\UpdatePlayerRequest;
use App\Models\Club;
use App\Models\Player;
use App\Services\PlayerService;
use App\Services\PlayersExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlayerController extends Controller
{
    public function __construct(
        protected PlayerService $playerService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Player::class);

        $players = $this->playerService->list($request->all());
        $clubs = Club::query()->orderBy('name')->get();

        return view('players.index', compact('players', 'clubs'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        Gate::authorize('create', Player::class);

        return view('players.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePlayerRequest $request): RedirectResponse
    {
        $player = $this->playerService->create($request->validated());

        return redirect()
            ->route('players.show', $player)
            ->with('success', __('players.flash.created'));
    }

    /**
     * Show the import form.
     */
    public function importForm(Request $request): View
    {
        Gate::authorize('create', Player::class);

        $user = $request->user();
        $clubs = $user->isAdmin()
            ? Club::query()->active()->orderBy('name')->get()
            : $user->managedClubs()->where('clubs.status', ClubStatus::Active->value)->orderBy('clubs.name')->get();

        return view('players.import', compact('clubs'));
    }

    /**
     * Handle the spreadsheet import.
     */
    public function import(Request $request, ImportPlayersAction $action): RedirectResponse
    {
        Gate::authorize('create', Player::class);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'club_id' => ['nullable', 'integer', 'exists:clubs,id'],
        ]);

        $club = null;
        if (! empty($validated['club_id'])) {
            $club = Club::query()->findOrFail((int) $validated['club_id']);
            Gate::authorize('manageMembers', $club);
        }

        $result = $action->execute($request->file('file'), $club);

        return redirect()
            ->route('players.import')
            ->with('import_result', $result);
    }

    /**
     * Export players registry to PDF, XLSX, or HTML.
     */
    public function export(string $format, Request $request, PlayersExport $exporter): Response|View|StreamedResponse
    {
        Gate::authorize('create', Player::class);

        $players = $this->playerService->all($request->all());

        return match ($format) {
            'xlsx' => $exporter->toXlsx($players),
            'pdf' => $exporter->toPdf($players),
            'html' => $exporter->toHtml($players),
            default => abort(404, __('errors.unsupported_export_format')),
        };
    }

    /**
     * Display the specified resource.
     */
    public function show(Player $player): View
    {
        Gate::authorize('view', $player);

        $player->load(['ratings', 'clubs']);

        return view('players.show', compact('player'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Player $player): View
    {
        Gate::authorize('update', $player);

        return view('players.edit', compact('player'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePlayerRequest $request, Player $player): RedirectResponse
    {
        Gate::authorize('update', $player);

        $data = $request->safe()->except('version');
        $version = (int) $request->input('version');

        $this->playerService->update($player, $data, $version);

        return redirect()
            ->route('players.show', $player)
            ->with('success', __('players.flash.updated'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Player $player): RedirectResponse
    {
        Gate::authorize('delete', $player);

        $this->playerService->delete($player);

        return redirect()
            ->route('players.index')
            ->with('success', __('players.flash.deleted'));
    }
}
