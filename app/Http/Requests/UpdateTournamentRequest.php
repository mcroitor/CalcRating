<?php

namespace App\Http\Requests;

use App\Enums\PairingType;
use App\Enums\RatingMode;
use App\Enums\TournamentStatus;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class UpdateTournamentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $tournament = $this->route('tournament');

        return $tournament instanceof Tournament && ($this->user()?->can('update', $tournament) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'pairing_type' => ['sometimes', 'required', new Enum(PairingType::class)],
            'rating_mode' => ['sometimes', 'nullable', new Enum(RatingMode::class)],
            'rounds_count' => ['sometimes', 'required', 'integer', 'min:1', 'max:50'],
            'games_per_round' => ['sometimes', 'required', 'integer', 'min:1', 'max:10'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'arbiter_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('users', 'id')->whereIn('role', ['arbiter', 'admin']),
            ],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $tournament = $this->route('tournament');

                if (! $tournament instanceof Tournament || $tournament->status === TournamentStatus::Draft) {
                    return;
                }

                foreach (['pairing_type', 'rating_mode', 'rounds_count', 'games_per_round'] as $field) {
                    if (! $this->has($field) || $this->input($field) === null) {
                        continue;
                    }

                    $new = (string) $this->input($field);
                    $old = $tournament->{$field} instanceof \BackedEnum
                        ? $tournament->{$field}->value
                        : (string) $tournament->{$field};

                    if ($new !== $old) {
                        $validator->errors()->add(
                            $field,
                            __('tournaments.errors.locked_parameters')
                        );
                    }
                }

                $arbiterId = $this->input('arbiter_id');
                if (is_numeric($arbiterId) && $tournament->club) {
                    $arbiter = User::query()->find((int) $arbiterId);
                    if ($arbiter && ! $arbiter->isAdmin() && ! $tournament->club->hasStaffUser($arbiter)) {
                        $validator->errors()->add('arbiter_id', __('clubs.errors.arbiter_not_in_club'));
                    }
                }
            },
        ];
    }
}
