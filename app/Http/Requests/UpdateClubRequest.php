<?php

namespace App\Http\Requests;

use App\Models\Club;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClubRequest extends FormRequest
{
    public function authorize(): bool
    {
        $club = $this->route('club');

        return $club instanceof Club && ($this->user()?->can('update', $club) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $club = $this->route('club');
        $clubId = $club instanceof Club ? $club->id : null;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('clubs', 'name')->ignore($clubId)],
            'code' => ['nullable', 'string', 'max:32', Rule::unique('clubs', 'code')->ignore($clubId)],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}
