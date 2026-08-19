<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreVehicleImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vehicle = Vehicle::find($this->route('vehicleId'));

        return $vehicle === null
            || ($this->user()?->can('update', $vehicle) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('files')) {
                    return;
                }

                $vehicle = Vehicle::query()
                    ->withCount('vehicleImages')
                    ->find($this->route('vehicleId'));

                if ($vehicle && $vehicle->vehicle_images_count + count($this->file('files', [])) > 5) {
                    $validator->errors()->add('files', 'A vehicle may have at most 5 images.');
                }
            },
        ];
    }
}
