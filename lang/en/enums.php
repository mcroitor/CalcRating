<?php

return [
    'chess_title' => [
        'none' => 'No category',
        'iv' => 'Category IV',
        'iii' => 'Category III',
        'ii' => 'Category II',
        'i' => 'Category I',
        'cm' => 'Candidate Master (CM)',
        'wcm' => 'Woman Candidate Master (WCM)',
        'fm' => 'FIDE Master (FM)',
        'wfm' => 'Woman FIDE Master (WFM)',
        'im' => 'International Master (IM)',
        'wim' => 'Woman International Master (WIM)',
        'gm' => 'Grandmaster (GM)',
        'wgm' => 'Woman Grandmaster (WGM)',
    ],

    'user_role' => [
        'guest' => 'Guest',
        'arbiter' => 'Arbiter',
        'admin' => 'Administrator',
    ],

    'tournament_status' => [
        'draft' => 'Draft',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
    ],

    'pairing_type' => [
        'round_robin' => 'Round-robin',
        'swiss' => 'Swiss',
        'olympic' => 'Olympic',
    ],

    'rating_mode' => [
        'average' => 'By average opponent rating',
        'per_game' => 'Per game',
        'average_short' => 'By average rating',
        'per_game_short' => 'Per game',
        'average_description' => 'The average opponent rating is calculated for the tournament, then the Elo formula is applied.',
        'per_game_description' => 'Expected points are calculated for each game separately and then summed.',
    ],

    'pairing_result' => [
        'white_win' => '1-0 (White wins)',
        'black_win' => '0-1 (Black wins)',
        'draw' => '½-½ (Draw)',
        'double_forfeit' => '0-0 (Double forfeit)',
        'pending' => 'Not played',
    ],

    'club_status' => [
        'active' => 'Active',
        'archived' => 'Archived',
    ],

    'club_staff_role' => [
        'manager' => 'Club manager',
        'arbiter' => 'Club arbiter',
    ],
];
