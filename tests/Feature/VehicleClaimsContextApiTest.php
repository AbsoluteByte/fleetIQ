<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Car;
use App\Models\Company;
use App\Models\Driver;
use App\Models\InsuranceProvider;
use App\Models\Status;
use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\SetupAgreementChangeCarDatabase;
use Tests\TestCase;

class VehicleClaimsContextApiTest extends TestCase
{
    use SetupAgreementChangeCarDatabase;

    private Tenant $tenant;

    private Company $company;

    private Car $car;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.claims_api.token' => 'test-claims-token',
            'services.claims_api.tenant_id' => null,
        ]);

        $this->setUpAgreementChangeCarDatabase();
        $this->extendSchema();

        $this->tenant = Tenant::query()->create(['company_name' => 'Claims API Tenant']);
        $this->company = Company::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fleet Co Ltd',
            'company_registration_number' => '12345678',
            'address_line_1' => '1 High Street',
            'town' => 'London',
            'postcode' => 'E1 1AA',
            'phone' => '02070000000',
            'email' => 'fleet@example.com',
        ]);

        $carModelId = DB::table('car_models')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Model',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->car = Car::query()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'car_model_id' => $carModelId,
            'registration' => 'AB12CDE',
            'fleet_status' => 'on_rent',
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('insurance_providers');
        $this->tearDownAgreementChangeCarDatabase();
        parent::tearDown();
    }

    private function extendSchema(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'company_registration_number')) {
                $table->string('company_registration_number')->nullable();
                $table->string('address_line_1')->nullable();
                $table->string('address_line_2')->nullable();
                $table->string('town')->nullable();
                $table->string('county')->nullable();
                $table->string('postcode')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
            }
        });

        Schema::create('insurance_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable();
            $table->string('provider_name')->nullable();
            $table->string('insurance_type')->nullable();
            $table->string('policy_number')->nullable();
            $table->foreignId('tenant_id')->nullable();
            $table->timestamps();
        });
    }

    private function claimsHeaders(): array
    {
        return [
            'Authorization' => 'Bearer test-claims-token',
            'X-Tenant-Id' => (string) $this->tenant->id,
            'Accept' => 'application/json',
        ];
    }

    public function test_returns_company_insurance_from_latest_agreement(): void
    {
        $driver = Driver::query()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Test',
            'last_name' => 'Driver',
        ]);
        $status = Status::query()->create(['name' => 'Active', 'type' => 'agreement']);
        $provider = InsuranceProvider::query()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'provider_name' => 'Acme Insurance',
            'policy_number' => 'POL-999',
            'insurance_type' => 'Comprehensive',
        ]);

        Agreement::query()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'driver_id' => $driver->id,
            'car_id' => $this->car->id,
            'status_id' => $status->id,
            'start_date' => '2025-01-01',
            'end_date' => '2026-01-01',
            'agreed_rent' => 100,
            'rent_interval' => 'weekly',
            'using_own_insurance' => false,
            'insurance_provider_id' => $provider->id,
        ]);

        $response = $this->getJson("/api/v1/vehicles/{$this->car->id}/claims-context", $this->claimsHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.insurance.source', 'company');
        $response->assertJsonPath('data.insurance.insurer_name', 'Acme Insurance');
        $response->assertJsonPath('data.insurance.policy_number', 'POL-999');
        $response->assertJsonPath('data.insurance.insurance_type', 'Comprehensive');
        $response->assertJsonPath('data.company.name', 'Fleet Co Ltd');
        $response->assertJsonPath('data.company.company_registration_number', '12345678');
    }

    public function test_returns_driver_own_insurance_when_agreement_uses_own_insurance(): void
    {
        $driver = Driver::query()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Test',
            'last_name' => 'Driver',
        ]);
        $status = Status::query()->create(['name' => 'Active', 'type' => 'agreement']);

        Agreement::query()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'driver_id' => $driver->id,
            'car_id' => $this->car->id,
            'status_id' => $status->id,
            'start_date' => '2026-06-01',
            'end_date' => '2027-06-01',
            'agreed_rent' => 100,
            'rent_interval' => 'weekly',
            'using_own_insurance' => true,
            'own_insurance_provider_name' => 'Driver Insure',
            'own_insurance_policy_number' => 'OWN-123',
            'own_insurance_type' => 'Third Party',
        ]);

        $response = $this->getJson("/api/v1/vehicles/{$this->car->id}/claims-context", $this->claimsHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.insurance.source', 'driver');
        $response->assertJsonPath('data.insurance.insurer_name', 'Driver Insure');
        $response->assertJsonPath('data.insurance.policy_number', 'OWN-123');
        $response->assertJsonPath('data.insurance.insurance_type', 'Third Party');
    }

    public function test_returns_404_for_unknown_vehicle(): void
    {
        $response = $this->getJson('/api/v1/vehicles/99999/claims-context', $this->claimsHeaders());

        $response->assertNotFound();
    }
}
