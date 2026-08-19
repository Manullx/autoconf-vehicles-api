<?php

namespace App\Http\Requests;

use App\Enums\Cambio;
use App\Enums\Combustivel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'placa' => ['required', 'alpha_num:ascii', Rule::unique('vehicles', 'placa'), 'regex:/^[A-Z]{3}\d[A-Z0-9]\d{2}$/i', 'size:7'],
            'chassi' => ['required', 'alpha_num:ascii', Rule::unique('vehicles', 'chassi'), 'size:17'],
            'marca' => ['required'],
            'modelo' => ['required'],
            'versao' => ['required'],
            'valor_venda' => ['required', 'numeric', 'decimal:2', 'min:0.01'],
            'cor' => ['required'],
            'km' => ['required', 'integer', 'min:0'],
            'cambio' => ['required', Rule::enum(Cambio::class)],
            'combustivel' => ['required', Rule::enum(Combustivel::class)],
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'cover_index' => ['required', 'integer', Rule::in(array_keys($this->file('files', [])))],
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
