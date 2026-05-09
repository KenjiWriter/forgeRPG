<?php

namespace App\Http\Requests\Forge;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationRule;

class ForgeInitRequest extends FormRequest
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
            'target_slot' => ['required', 'string', 'in:helmet,armor,pants,boots,weapon,pickaxe'],
            'ore_inputs' => ['required', 'array', 'size:3'],
            'ore_inputs.*.ore_type_id' => ['required', 'integer', 'exists:ore_types,id'],
            'ore_inputs.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
