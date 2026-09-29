<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agreement;
use App\Models\Car;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleClaimsContextController extends Controller
{
    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('claims_tenant_id');

        $car = Car::query()
            ->with(['company.country'])
            ->where('tenant_id', $tenantId)
            ->find($id);

        if (! $car) {
            return response()->json(['message' => 'Vehicle not found'], 404);
        }

        $agreement = Agreement::query()
            ->with('insuranceProvider')
            ->where('tenant_id', $tenantId)
            ->where('car_id', $car->id)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'data' => [
                'insurance' => $this->insurancePayload($agreement),
                'company' => $this->companyPayload($car->company),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function insurancePayload(?Agreement $agreement): ?array
    {
        if (! $agreement) {
            return null;
        }

        if ($agreement->using_own_insurance) {
            $name = trim((string) ($agreement->own_insurance_provider_name ?? ''));
            $policy = trim((string) ($agreement->own_insurance_policy_number ?? ''));
            $type = trim((string) ($agreement->own_insurance_type ?? ''));

            if ($name === '' && $policy === '' && $type === '') {
                return null;
            }

            return [
                'source' => 'driver',
                'insurer_name' => $name !== '' ? $name : null,
                'policy_number' => $policy !== '' ? $policy : null,
                'insurance_type' => $type !== '' ? $type : null,
            ];
        }

        $provider = $agreement->insuranceProvider;
        if (! $provider) {
            return null;
        }

        $name = trim((string) ($provider->provider_name ?? ''));
        $policy = trim((string) ($provider->policy_number ?? ''));
        $type = trim((string) ($provider->insurance_type ?? ''));

        if ($name === '' && $policy === '' && $type === '') {
            return null;
        }

        return [
            'source' => 'company',
            'insurer_name' => $name !== '' ? $name : null,
            'policy_number' => $policy !== '' ? $policy : null,
            'insurance_type' => $type !== '' ? $type : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function companyPayload(?Company $company): ?array
    {
        if (! $company) {
            return null;
        }

        $countryName = optional($company->country)->name ?? 'United Kingdom';

        return [
            'name' => $company->name,
            'company_registration_number' => $company->company_registration_number,
            'phone' => $company->phone,
            'email' => $company->email,
            'address_line1' => $company->address_line_1,
            'address_line2' => $company->address_line_2,
            'town' => $company->town,
            'county' => $company->county,
            'postcode' => $company->postcode,
            'country' => $countryName,
        ];
    }
}
