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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

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

        $sorts = explode(',', $filters['sort'] ?? 'id');

        foreach ($sorts as $sort) {
            $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $query->orderBy(ltrim($sort, '-'), $direction);
        }

        if (! in_array('id', array_map(fn (string $sort): string => ltrim($sort, '-'), $sorts), true)) {
            $query->orderBy('id');
        }

        return $query->paginate($filters['per_page'] ?? 15)->withQueryString();
    }

    public function store(StoreVehicleRequest $request): JsonResponse
    {
        Gate::authorize('create', Vehicle::class);
        $storedPaths = [];

        try {
            $vehicle = DB::transaction(function () use ($request, &$storedPaths): Vehicle {
                $vehicle = Vehicle::create([
                    'user_id' => $request->user()->id,
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                    'active' => true,
                    ...$request->safe()->except(['files', 'cover_index']),
                ]);

                foreach ($request->file('files', []) as $index => $file) {
                    $path = $file->store("vehicles/{$vehicle->id}", 'public');

                    if ($path === false) {
                        throw new RuntimeException('The vehicle image file could not be stored.');
                    }

                    $storedPaths[] = $path;

                    $vehicle->vehicleImages()->create([
                        'path' => $path,
                        'is_cover' => $index === $request->integer('cover_index'),
                    ]);
                }

                return $vehicle;
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);

            throw $exception;
        }

        return response()->json($vehicle->load($this->detailRelations()), 201);
    }

    public function show(Vehicle $vehicle): Vehicle
    {
        Gate::authorize('view', $vehicle);

        return $vehicle->load($this->detailRelations());
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): Vehicle
    {
        Gate::authorize('update', $vehicle);

        $vehicle->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return $vehicle->refresh()->load($this->detailRelations());
    }

    public function destroy(Vehicle $vehicle): Response
    {
        Gate::authorize('delete', $vehicle);

        $paths = DB::transaction(function () use ($vehicle): array {
            $lockedVehicle = Vehicle::query()->lockForUpdate()->findOrFail($vehicle->id);
            $paths = $lockedVehicle->vehicleImages()->lockForUpdate()->pluck('path')->all();

            $lockedVehicle->delete();

            return $paths;
        });

        if ($paths !== [] && ! Storage::disk('public')->delete($paths)) {
            throw new RuntimeException('The vehicle image files could not be deleted.');
        }

        return response()->noContent();
    }

    /**
     * @return array<int, string>
     */
    private function detailRelations(): array
    {
        return [
            'vehicleImages',
            'creator:id,name,email',
            'updater:id,name,email',
        ];
    }
}
