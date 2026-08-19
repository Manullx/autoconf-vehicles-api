<?php

namespace App\Http\Requests;

use App\Enums\Cambio;
use App\Enums\Combustivel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedIdentifiers());
    }

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
            'placa' => [$presenceRule, 'alpha_num:ascii', Rule::unique('vehicles', 'placa')->ignore($vehicle), 'regex:/^[A-Z]{3}\d[A-Z0-9]\d{2}$/i', 'size:7'],
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

    /**
     * @return array<string, string>
     */
    private function normalizedIdentifiers(): array
    {
        return collect(['placa', 'chassi'])
            ->filter(fn (string $field): bool => is_string($this->input($field)))
            ->mapWithKeys(fn (string $field): array => [$field => strtoupper($this->input($field))])
            ->all();
    }
}
