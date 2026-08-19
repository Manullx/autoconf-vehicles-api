<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVehicleImagesRequest;
use App\Models\Vehicle;
use App\Models\VehicleImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
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
                $lockedVehicle = Vehicle::query()->lockForUpdate()->findOrFail($vehicle->id);
                $existingImages = $lockedVehicle->vehicleImages()->lockForUpdate()->get();
                $uploadedFiles = $request->file('files', []);

                if ($existingImages->count() + count($uploadedFiles) > 5) {
                    throw ValidationException::withMessages([
                        'files' => 'A vehicle may have at most 5 images.',
                    ]);
                }

                $cover = $existingImages->firstWhere('is_cover', true);

                if ($existingImages->where('is_cover', true)->count() > 1) {
                    $lockedVehicle->vehicleImages()->update(['is_cover' => false]);
                    VehicleImage::query()->whereKey($cover->id)->update(['is_cover' => true]);
                } elseif ($cover === null && $existingImages->isNotEmpty()) {
                    $cover = $existingImages->first();
                    $cover->update(['is_cover' => true]);
                }

                $hasCover = $cover !== null;
                $images = collect($uploadedFiles)->map(function ($file) use ($lockedVehicle, &$hasCover, &$storedPaths): VehicleImage {
                    $path = $file->store("vehicles/{$lockedVehicle->id}", 'public');

                    if ($path === false) {
                        throw new RuntimeException('The vehicle image file could not be stored.');
                    }

                    $storedPaths[] = $path;

                    $image = $lockedVehicle->vehicleImages()->create([
                        'path' => $path,
                        'is_cover' => ! $hasCover,
                    ]);

                    $hasCover = true;

                    return $image;
                });

                $lockedVehicle->forceFill(['updated_by' => $request->user()->id])->save();

                return $images;
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);

            throw $exception;
        }

        return response()->json($images, 201);
    }

    public function cover(Request $request, int $vehicleId, int $imageId): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($vehicleId);
        Gate::authorize('update', $vehicle);

        $image = DB::transaction(function () use ($request, $vehicleId, $imageId): VehicleImage {
            $lockedVehicle = Vehicle::query()->lockForUpdate()->findOrFail($vehicleId);
            $images = $lockedVehicle->vehicleImages()->lockForUpdate()->get();

            $image = $images->firstWhere('id', $imageId);

            abort_if($image === null, 404);

            $lockedVehicle->vehicleImages()->update(['is_cover' => false]);

            VehicleImage::query()->whereKey($image->id)->update(['is_cover' => true]);
            $lockedVehicle->forceFill(['updated_by' => $request->user()->id])->save();

            return $image->refresh();
        });

        return response()->json($image);
    }

    public function destroy(Request $request, int $vehicleId, int $imageId): Response
    {
        $vehicle = Vehicle::findOrFail($vehicleId);
        Gate::authorize('update', $vehicle);

        $path = DB::transaction(function () use ($request, $vehicleId, $imageId): string {
            $lockedVehicle = Vehicle::query()->lockForUpdate()->findOrFail($vehicleId);
            $images = $lockedVehicle->vehicleImages()->lockForUpdate()->get();
            $image = $images->firstWhere('id', $imageId);

            abort_if($image === null, 404);

            if ($images->count() === 1) {
                throw ValidationException::withMessages([
                    'image' => 'The only image of a vehicle cannot be deleted.',
                ]);
            }

            $remainingImages = $images->where('id', '!=', $image->id);
            $cover = $image->is_cover
                ? $remainingImages->first()
                : ($remainingImages->firstWhere('is_cover', true) ?? $remainingImages->first());

            $lockedVehicle->vehicleImages()->update(['is_cover' => false]);
            VehicleImage::query()->whereKey($cover->id)->update(['is_cover' => true]);
            $image->delete();

            $lockedVehicle->forceFill(['updated_by' => $request->user()->id])->save();

            return $image->path;
        });

        if (! Storage::disk('public')->delete($path)) {
            throw new RuntimeException('The vehicle image file could not be deleted.');
        }

        return response()->noContent();
    }
}
