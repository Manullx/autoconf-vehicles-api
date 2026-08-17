<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ListVehiclesRequest extends FormRequest
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
        $sortableFields = 'id|placa|marca|modelo|km|valor_venda|created_at';

        return [
            'q' => ['sometimes', 'string', 'max:255'],
            'placa' => ['sometimes', 'string', 'max:7'],
            'marca' => ['sometimes', 'string', 'max:255'],
            'modelo' => ['sometimes', 'string', 'max:255'],
            'sort' => ['sometimes', 'string', "regex:/^-?(?:{$sortableFields})(?:,-?(?:{$sortableFields}))*$/"],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
