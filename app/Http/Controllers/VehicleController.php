<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListVehiclesRequest;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\Vehicle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class VehicleController extends Controller
{
    public function index(ListVehiclesRequest $request): LengthAwarePaginator
    {
        $filters = $request->validated();
        $query = Vehicle::query()->with('vehicleImages');

        Gate::authorize('viewAny', Vehicle::class);

        if (! $request->user()->is_admin) {
            $query->where('user_id', $request->user()->id);
        }

        if (isset($filters['q'])) {
            $search = $filters['q'];

            $query->where(function (Builder $query) use ($search) {
                $query->where('placa', 'like', "%{$search}%")
                    ->orWhere('marca', 'like', "%{$search}%")
                    ->orWhere('modelo', 'like', "%{$search}%");
            });
        }

        foreach (['placa', 'marca', 'modelo'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, 'like', "%{$filters[$field]}%");
            }
        }

        foreach (explode(',', $filters['sort'] ?? 'id') as $sort) {
            $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $query->orderBy(ltrim($sort, '-'), $direction);
        }

        return $query->paginate($filters['per_page'] ?? 15)->withQueryString();
    }

    public function store(StoreVehicleRequest $request): JsonResponse
    {
        Gate::authorize('create', Vehicle::class);

        $vehicle = Vehicle::create([
            'user_id' => $request->user()->id,
            'active' => true,
            ...$request->validated(),
        ]);

        return response()->json($vehicle, 201);
    }

    public function show(Vehicle $vehicle): Vehicle
    {
        Gate::authorize('view', $vehicle);

        return $vehicle->load('vehicleImages');
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): Vehicle
    {
        Gate::authorize('update', $vehicle);

        $vehicle->update($request->validated());

        return $vehicle->refresh();
    }

    public function destroy(Vehicle $vehicle): Response
    {
        Gate::authorize('delete', $vehicle);

        $vehicle->active = false;
        $vehicle->save();

        return response()->noContent();
    }
}
