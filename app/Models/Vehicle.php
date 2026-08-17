<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{

    protected $fillable = [
        'active',
        'placa',
        'chassi',
        'marca',
        'modelo',
        'versao',
        'valor_venda',
        'cor',
        'km',
        'cambio',
        'combustivel'
    ];

    public function vehicleImages(): HasMany {

        return $this->hasMany(VehicleImage::class);
    }
}