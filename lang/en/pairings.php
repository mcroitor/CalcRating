<?php

return [
    'flash' => [
        'generated_rounds' => 'Schedule generated: :rounds rounds.',
        'generated_round' => 'Round generated.',
        'result_saved' => 'Game result saved.',
    ],

    'errors' => [
        'only_in_progress' => 'Results can only be entered for a tournament in progress.',
        'bye_no_result' => 'A bye result is not entered: the participant receives 1 point automatically.',
        'pending_not_saved' => 'Cannot save the result "Not played".',
        'advanced_required' => 'When the score is tied, specify the participant advancing to the next round.',
        'advanced_must_be_opponent' => 'The advancing participant must be one of the opponents in this pairing.',
        'generation_only_in_progress' => 'Pairings are only available for a tournament in progress.',
        'min_participants' => 'At least two participants are required for pairing.',
        'round_robin_generated' => 'The round-robin schedule has already been generated.',
        'finish_current_round' => 'Finish all games of the current round before generating the next one.',
        'all_rounds_generated' => 'All tournament rounds have already been generated.',
        'finish_round_and_advance' => 'Finish all games of the current round and specify the advancing participant in case of a draw.',
        'final_round_generated' => 'The final round has already been generated.',
        'not_enough_participants_next' => 'Not enough participants for the next round.',
    ],
];
