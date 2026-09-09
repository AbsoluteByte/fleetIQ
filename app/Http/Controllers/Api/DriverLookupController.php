<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverLookupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('claims_tenant_id');
        $q = trim((string) $request->query('q', ''));

        $query = Driver::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name');

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($builder) use ($like, $q) {
                $builder->where('first_name', 'like', $like)
                    ->orWhere('middle_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone_number', 'like', $like)
                    ->orWhere('driver_license_number', 'like', $like)
                    ->orWhere('ni_number', 'like', $like)
                    ->orWhere('post_code', 'like', $like)
                    ->orWhereRaw("CONCAT_WS(' ', first_name, middle_name, last_name) LIKE ?", [$like]);

                if (ctype_digit($q)) {
                    $builder->orWhere('id', (int) $q);
                }
            });
        }

        $drivers = $query->limit(25)->get()->map(fn (Driver $driver) => $this->transform($driver));

        return response()->json(['data' => $drivers]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('claims_tenant_id');

        $driver = Driver::query()
            ->where('tenant_id', $tenantId)
            ->find($id);

        if (! $driver) {
            return response()->json(['message' => 'Driver not found'], 404);
        }

        return response()->json(['data' => $this->transform($driver)]);
    }

    private function transform(Driver $driver): array
    {
        return [
            'id' => $driver->id,
            'first_name' => $driver->first_name,
            'middle_name' => $driver->middle_name,
            'last_name' => $driver->last_name,
            'full_name' => $driver->full_name,
            'label' => $driver->selectOptionLabel(),
            'email' => $driver->email,
            'phone' => $driver->phone_number,
            'date_of_birth' => optional($driver->dob)?->format('Y-m-d'),
            'ni_number' => $driver->ni_number,
            'driver_license_number' => $driver->driver_license_number,
            'driver_license_expiry' => optional($driver->driver_license_expiry_date)?->format('Y-m-d'),
            'taxi_license_number' => $driver->phd_license_number,
            'taxi_license_expiry' => optional($driver->phd_license_expiry_date)?->format('Y-m-d'),
            'address_line1' => $driver->address1,
            'address_line2' => $driver->address2,
            'town' => $driver->town,
            'county' => $driver->county,
            'postcode' => $driver->post_code,
            'country' => 'United Kingdom',
        ];
    }
}
