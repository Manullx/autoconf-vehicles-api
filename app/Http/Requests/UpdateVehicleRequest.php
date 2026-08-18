<?php

namespace App\Http\Requests;

use App\Enums\Cambio;
use App\Enums\Combustivel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
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
        $presenceRule = $this->isMethod('patch') ? 'sometimes' : 'required';
        $vehicle = $this->route('vehicle');

        return [
            'placa' => [$presenceRule, 'alpha_num:ascii', Rule::unique('vehicles', 'placa')->ignore($vehicle), 'regex:/^[A-Z]{3}\d[A-Z]\d{2}$/i', 'size:7'],
            'chassi' => [$presenceRule, 'alpha_num:ascii', Rule::unique('vehicles', 'chassi')->ignore($vehicle), 'size:17'],
            'marca' => [$presenceRule],
            'modelo' => [$presenceRule],
            'versao' => [$presenceRule],
            'valor_venda' => [$presenceRule, 'numeric', 'decimal:2', 'min:0.01'],
            'cor' => [$presenceRule],
            'km' => [$presenceRule, 'integer', 'min:0'],
            'cambio' => [$presenceRule, Rule::enum(Cambio::class)],
            'combustivel' => [$presenceRule, Rule::enum(Combustivel::class)],
        ];
    }
}
