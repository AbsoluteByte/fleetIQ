<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Car;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Status;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\Concerns\SetupAgreementChangeCarDatabase;
use Tests\TestCase;

class AgreementDepositPreviewTest extends TestCase
{
    use SetupAgreementChangeCarDatabase;

    private Tenant $tenant;

    private Company $company;

    private Driver $driver;

    private Car $car;

    private Status $terminatedStatus;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(RoleMiddleware::class);
        $this->setUpAgreementChangeCarDatabase();
        $this->setUpHttpTestExtras();

        $this->tenant = Tenant::query()->create([
            'company_name' => 'Deposit Preview Tenant',
            'status' => Tenant::STATUS_ACTIVE,
        ]);
        $this->company = Company::query()->create(['tenant_id' => $this->tenant->id, 'name' => 'Fleet Co']);
        $this->driver = Driver::query()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Preview',
            'last_name' => 'Driver',
            'email' => 'preview-driver@example.com',
            'phone_number' => '07000000009',
        ]);

        $carModelId = (int) DB::table('car_models')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Model',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counselId = (int) DB::table('counsels')->insertGetId([
            'name' => 'Test Counsel',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->car = $this->createCar($this->tenant->id, $this->company->id, $carModelId, $counselId);
        $this->terminatedStatus = Status::query()->create(['name' => 'Terminated', 'type' => 'agreement']);

        $this->user = User::factory()->create();
        $this->user->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_primary' => true,
            'joined_at' => now(),
        ]);
        $this->actingAs($this->user);
        $this->user->switchTenant($this->tenant->id);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('deposit_refunds');
        Schema::dropIfExists('agreement_deductions');
        Schema::dropIfExists('agreement_collections');
        Schema::dropIfExists('car_status_histories');
        Schema::dropIfExists('car_services');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('tenant_user');
        $this->tearDownAgreementChangeCarDatabase();

        parent::tearDown();
    }

    public function test_deposit_settlement_preview_returns_json_for_eligible_agreement(): void
    {
        $agreement = Agreement::query()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'driver_id' => $this->driver->id,
            'car_id' => $this->car->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-01',
            'closing_date' => '2026-06-01',
            'agreed_rent' => 150,
            'rent_interval' => 'Weekly',
            'deposit_amount' => 300,
            'collection_type' => 'weekly',
            'status_id' => $this->terminatedStatus->id,
        ]);

        Invoice::query()->create([
            'driver_id' => $this->driver->id,
            'invoice_type' => 'manual',
            'invoice_no' => 'INV-PREVIEW-1',
            'invoice_date' => '2026-06-01',
            'due_date' => '2026-06-08',
            'total_amount' => 50,
            'paid_amount' => 0,
            'balance_amount' => 50,
            'status' => 'pending',
        ]);

        $response = $this->getJson(route('agreements.deposit-settlement-preview', $agreement));

        $response->assertOk();
        $response->assertJsonStructure([
            'gross_deposit_amount',
            'deductions_amount',
            'driver_outstanding_amount',
            'debt_offset_amount',
            'remaining_debt_amount',
            'refund_amount',
        ]);
        $response->assertJsonFragment(['gross_deposit_amount' => 300.0]);
        $response->assertJsonFragment(['driver_outstanding_amount' => 50.0]);
        $response->assertJsonFragment(['refund_amount' => 250.0]);
    }

    private function setUpHttpTestExtras(): void
    {
        if (! Schema::hasColumn('tenants', 'status')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->unsignedTinyInteger('status')->default(1);
            });
        }

        if (! Schema::hasColumn('car_reservations', 'tenant_id')) {
            Schema::table('car_reservations', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable();
            });
        }

        if (! Schema::hasTable('tenant_user')) {
            Schema::create('tenant_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('user_id');
                $table->string('role')->default('admin');
                $table->boolean('is_primary')->default(true);
                $table->timestamp('joined_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
            });

            DB::table('roles')->insert([
                'name' => 'admin',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->primary(['role_id', 'model_id', 'model_type']);
            });
        }

        if (! Schema::hasTable('agreement_deductions')) {
            Schema::create('agreement_deductions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('agreement_id');
                $table->decimal('amount', 12, 2);
                $table->text('notes')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->foreignId('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('deposit_refunds')) {
            Schema::create('deposit_refunds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('agreement_id')->unique();
                $table->foreignId('driver_id');
                $table->decimal('amount', 12, 2)->default(0);
                $table->decimal('gross_deposit_amount', 12, 2)->default(0);
                $table->decimal('deductions_amount', 12, 2)->default(0);
                $table->decimal('debt_offset_amount', 12, 2)->default(0);
                $table->string('payment_method')->nullable();
                $table->string('status')->default('pending');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bank_accounts')) {
            Schema::create('bank_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('company_id');
                $table->string('bank_name');
                $table->string('account_number', 50);
                $table->unsignedBigInteger('createdBy')->nullable();
                $table->unsignedBigInteger('updatedBy')->nullable();
                $table->timestamps();
            });
        }
    }

    private function createCar(int $tenantId, int $companyId, int $carModelId, int $counselId): Car
    {
        $car = Car::query()->create([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'car_model_id' => $carModelId,
            'registration' => 'TN123',
            'color' => 'Black',
            'vin' => 'VINTN123',
            'manufacture_year' => 2020,
            'registration_year' => 2020,
            'purchase_date' => '2020-01-01',
            'purchase_price' => 10000,
            'purchase_type' => 'uk',
            'fleet_status' => Car::FLEET_STATUS_AVAILABLE_FOR_RENT,
        ]);

        DB::table('car_mots')->insert([
            'car_id' => $car->id,
            'expiry_date' => '2027-01-01',
            'amount' => 50,
            'term' => '12 months',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('car_road_taxes')->insert([
            'car_id' => $car->id,
            'start_date' => '2026-01-01',
            'term' => '12 months',
            'amount' => 180,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('car_phvs')->insert([
            'car_id' => $car->id,
            'counsel_id' => $counselId,
            'amount' => 200,
            'start_date' => '2026-01-01',
            'expiry_date' => '2027-01-01',
            'notify_before_expiry' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $car;
    }
}
