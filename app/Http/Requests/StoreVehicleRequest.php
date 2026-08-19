<?php

namespace App\Http\Requests;

use App\Enums\Cambio;
use App\Enums\Combustivel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'placa' => ['required', 'alpha_num:ascii', Rule::unique('vehicles', 'placa'), 'regex:/^[A-Z]{3}\d[A-Z]\d{2}$/i', 'size:7'],
            'chassi' => ['required', 'alpha_num:ascii', Rule::unique('vehicles', 'chassi'), 'size:17'],
            'marca' => ['required'],
            'modelo' => ['required'],
            'versao' => ['required'],
            'valor_venda' => ['required', 'numeric', 'decimal:2', 'min:0.01'],
            'cor' => ['required'],
            'km' => ['required', 'integer', 'min:0'],
            'cambio' => ['required', Rule::enum(Cambio::class)],
            'combustivel' => ['required', Rule::enum(Combustivel::class)],
            'files' => ['sometimes', 'array', 'max:5'],
            'files.*' => ['required', 'image', 'max:204800'],
            'cover_index' => ['required_with:files', 'integer', Rule::in(array_keys($this->file('files', [])))],
        ];
    }
}
