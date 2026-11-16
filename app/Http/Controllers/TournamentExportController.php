<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\StandingsService;
use App\Services\TournamentExport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TournamentExportController extends Controller
{
    public function __construct(
        protected StandingsService $standings,
        protected TournamentExport $export
    ) {}

    public function export(Request $request, Tournament $tournament, string $format): Response|StreamedResponse|View
    {
        Gate::authorize('export', $tournament);

        $tournament->load(['club', 'participants.player', 'pairings.whitePlayer', 'pairings.blackPlayer', 'pairings.advancedPlayer', 'arbiter']);
        $standings = $this->standings->build($tournament);
        $pairingsByRound = $tournament->pairings->groupBy('round_number');

        switch ($format) {
            case 'xlsx':
                return $this->export->toXlsx($tournament, $standings, $pairingsByRound, 'tournament-'.$tournament->id.'.xlsx');
            case 'pdf':
                return $this->export->toPdf($tournament, $standings, $pairingsByRound, 'tournament-'.$tournament->id.'.pdf');
            case 'html':
                return $this->export->toHtml($tournament, $standings, $pairingsByRound);
            default:
                abort(404);
        }
    }
}
