<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVehicleImagesRequest;
use App\Models\Vehicle;
use App\Models\VehicleImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Throwable;

class VehicleImageController extends Controller
{
    public function store(StoreVehicleImagesRequest $request, int $vehicleId): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($vehicleId);
        Gate::authorize('update', $vehicle);
        $storedPaths = [];

        try {
            $images = DB::transaction(function () use ($request, $vehicle, &$storedPaths) {
                $hasCover = $vehicle->vehicleImages()->where('is_cover', true)->exists();

                return collect($request->file('files'))->map(function ($file) use ($vehicle, &$hasCover, &$storedPaths): VehicleImage {
                    $path = $file->store("vehicles/{$vehicle->id}", 'public');
                    $storedPaths[] = $path;

                    $image = $vehicle->vehicleImages()->create([
                        'path' => $path,
                        'is_cover' => ! $hasCover,
                    ]);

                    $hasCover = true;

                    return $image;
                });
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);

            throw $exception;
        }

        return response()->json($images, 201);
    }

    public function cover(int $vehicleId, int $imageId): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($vehicleId);
        Gate::authorize('update', $vehicle);

        $image = DB::transaction(function () use ($vehicleId, $imageId): VehicleImage {
            $images = VehicleImage::query()
                ->where('vehicle_id', $vehicleId)
                ->lockForUpdate()
                ->get();

            $image = $images->firstWhere('id', $imageId);

            abort_if($image === null, 404);

            VehicleImage::query()
                ->where('vehicle_id', $vehicleId)
                ->where('is_cover', true)
                ->update(['is_cover' => false]);

            $image->update(['is_cover' => true]);

            return $image->refresh();
        });

        return response()->json($image);
    }

    public function destroy(int $vehicleId, int $imageId): Response
    {
        $vehicle = Vehicle::findOrFail($vehicleId);
        Gate::authorize('update', $vehicle);

        $image = VehicleImage::query()
            ->where('vehicle_id', $vehicleId)
            ->findOrFail($imageId);

        DB::transaction(function () use ($image): void {
            Storage::disk('public')->delete($image->path);
            $image->delete();
        });

        return response()->noContent();
    }
}
