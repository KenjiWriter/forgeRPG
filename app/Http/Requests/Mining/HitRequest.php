<?php

namespace App\Http\Requests\Mining;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class HitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'node_id' => ['required', 'integer', 'exists:mining_nodes,id'],
            'stamina_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
