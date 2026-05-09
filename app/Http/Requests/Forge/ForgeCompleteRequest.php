<?php

namespace App\Http\Requests\Forge;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationRule;

class ForgeCompleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'forge_session_id' => ['required', 'string', 'exists:forge_sessions,id'],
            'smelting_score' => ['required', 'integer', 'min:0', 'max:100'],
            'smithing_score' => ['required', 'integer', 'min:0', 'max:100'],
            'quench_score' => ['required', 'integer', 'min:0', 'max:100'],
            'item_name' => ['required', 'string', 'max:255'],
        ];
    }
}
