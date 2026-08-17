<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\Vehicle;

class VehicleController extends Controller
{
    public function store(StoreVehicleRequest $request): Vehicle
    {
        $vehicle = Vehicle::create([
            'active' => true,
            ...$request->validated(),
        ]);

        return $vehicle;
    }

    public function findAll()
    {

        return Vehicle::all();
    }

    public function findOne(string $id): Vehicle
    {

        $vehicle = Vehicle::where('id', $id)->first();

        return $vehicle;
    }

    public function remove(string $id): Vehicle
    {

        $vehicle = Vehicle::where('id', $id)->first();
        $vehicle->active = false;
        $vehicle->save();

        return $vehicle;
    }

    public function update(UpdateVehicleRequest $request, string $id): Vehicle
    {

        $vehicle = Vehicle::findOrFail($id);
        $vehicle->update($request->validated());

        return $vehicle->refresh();
    }

    public function patch(UpdateVehicleRequest $request, string $id): Vehicle
    {

        return $this->update($request, $id);
    }
}
