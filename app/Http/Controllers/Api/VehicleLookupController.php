<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Car;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleLookupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('claims_tenant_id');
        $q = trim((string) $request->query('q', ''));

        $query = Car::query()
            ->with(['carModel:id,name', 'company:id,name'])
            ->where('tenant_id', $tenantId)
            ->orderBy('registration');

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('registration', 'like', $like)
                    ->orWhere('vin', 'like', $like)
                    ->orWhere('color', 'like', $like)
                    ->orWhereHas('carModel', fn ($m) => $m->where('name', 'like', $like));
            });
        }

        $cars = $query->limit(25)->get()->map(fn (Car $car) => $this->transform($car));

        return response()->json(['data' => $cars]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('claims_tenant_id');

        $car = Car::query()
            ->with(['carModel:id,name', 'company:id,name'])
            ->where('tenant_id', $tenantId)
            ->find($id);

        if (! $car) {
            return response()->json(['message' => 'Vehicle not found'], 404);
        }

        return response()->json(['data' => $this->transform($car)]);
    }

    private function transform(Car $car): array
    {
        $makeModel = trim((string) optional($car->carModel)->name);
        [$make, $model] = $this->splitMakeModel($makeModel);

        return [
            'id' => $car->id,
            'registration' => $car->registration,
            'make' => $make,
            'model' => $model,
            'make_model' => $makeModel !== '' ? $makeModel : null,
            'year' => $car->manufacture_year ?? $car->registration_year,
            'colour' => $car->color,
            'vin' => $car->vin,
            'fleet_status' => $car->fleet_status,
            'company' => optional($car->company)->name,
        ];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function splitMakeModel(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return [null, null];
        }

        $parts = preg_split('/\s+/', $name, 2);

        return [$parts[0] ?? null, $parts[1] ?? null];
    }
}
