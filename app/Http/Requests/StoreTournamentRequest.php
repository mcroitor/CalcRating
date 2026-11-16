<?php

namespace App\Http\Requests;

use App\Enums\ClubStatus;
use App\Enums\PairingType;
use App\Enums\RatingMode;
use App\Models\Club;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StoreTournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Tournament::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'club_id' => [
                'required',
                'integer',
                Rule::exists('clubs', 'id')->where('status', ClubStatus::Active->value),
            ],
            'name' => ['required', 'string', 'max:255'],
            'pairing_type' => ['required', new Enum(PairingType::class)],
            'rating_mode' => ['nullable', new Enum(RatingMode::class)],
            'rounds_count' => ['required', 'integer', 'min:1', 'max:50'],
            'games_per_round' => ['required', 'integer', 'min:1', 'max:10'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'arbiter_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')
                    ->whereIn('role', ['arbiter', 'admin'])
                    ->where('is_blocked', 0),
            ],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $clubId = $this->input('club_id');
                if (! is_numeric($clubId)) {
                    return;
                }

                $club = Club::query()->find((int) $clubId);
                if (! $club instanceof Club) {
                    return;
                }

                if (! Gate::forUser($this->user())->allows('createTournament', $club)) {
                    $validator->errors()->add('club_id', __('clubs.errors.cannot_create_tournament'));

                    return;
                }

                $arbiterId = $this->input('arbiter_id');
                if (is_numeric($arbiterId)) {
                    $arbiter = User::query()->find((int) $arbiterId);
                    if ($arbiter && ! $arbiter->isAdmin() && ! $club->hasStaffUser($arbiter)) {
                        $validator->errors()->add('arbiter_id', __('clubs.errors.arbiter_not_in_club'));
                    }
                }
            },
        ];
    }
}
