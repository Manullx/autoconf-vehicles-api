<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Vehicle;

class VehicleController extends Controller
{
    
    public function store(Request $request): Vehicle {

        $vehicle = Vehicle::create([
            'active' => true,
            'placa' => $request->input('placa'),
            'chassi' => $request->input('chassi'),
            'marca' => $request->input('marca'),
            'modelo' => $request->input('modelo'),
            'versao' => $request->input('versao'),
            'valor_venda' => $request->input('valor_venda'),
            'cor' => $request->input('cor'),
            'km' => $request->input('km'),
            'cambio' => $request->input('cambio'),
            'combustivel' => $request->input('combustivel')
        ]);

        return $vehicle;
    }

    public function findAll() {

        return Vehicle::all();
    }

    public function findOne(string $id): Vehicle {
        
        $vehicle = Vehicle::where('id', $id)->first();

        return $vehicle;
    }

    public function remove(string $id): Vehicle {
        
        $vehicle = Vehicle::where('id', $id)->first();
        $vehicle->active = false;
        $vehicle->save();

        return $vehicle;
    }
}
