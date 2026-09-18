<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Car;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Status;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\Concerns\SetupAgreementChangeCarDatabase;
use Tests\TestCase;

class AgreementIndexPerformanceTest extends TestCase
{
    use SetupAgreementChangeCarDatabase;

    private Tenant $tenant;

    private Company $company;

    private Driver $driver;

    private Car $car;

    private Status $activeStatus;

    private Status $terminatedStatus;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(RoleMiddleware::class);
        $this->setUpAgreementChangeCarDatabase();
        $this->setUpHttpTestExtras();

        $this->tenant = Tenant::query()->create([
            'company_name' => 'Agreement Index Performance Tenant',
            'status' => Tenant::STATUS_ACTIVE,
        ]);
        $this->company = Company::query()->create(['tenant_id' => $this->tenant->id, 'name' => 'Fleet Co']);
        $this->driver = Driver::query()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Sam',
            'last_name' => 'Driver',
            'email' => 'sam-perf@example.com',
            'phone_number' => '07000000008',
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
        $this->activeStatus = Status::query()->create(['name' => 'Active', 'type' => 'agreement']);
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

    public function test_agreements_index_search_finds_driver_by_full_name_and_postcode(): void
    {
        $this->driver->update([
            'first_name' => 'Jane',
            'last_name' => 'Postsearch',
            'post_code' => 'SW1A 1AA',
        ]);
        $otherDriver = Driver::query()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Other',
            'last_name' => 'Person',
            'email' => 'other-postsearch@example.com',
            'phone_number' => '07000000009',
            'post_code' => 'M1 1AE',
        ]);
        $this->createAgreement(['status_id' => $this->activeStatus->id, 'driver_id' => $this->driver->id]);
        $this->createAgreement(['status_id' => $this->activeStatus->id, 'driver_id' => $otherDriver->id]);

        $byFullName = $this->agreementsDatatableResponse(['search' => ['value' => 'Jane Postsearch']]);
        $byFullName->assertOk();
        $byFullName->assertJsonCount(1, 'data');

        $byPostcodeSpaced = $this->agreementsDatatableResponse(['search' => ['value' => 'SW1A 1AA']]);
        $byPostcodeSpaced->assertOk();
        $byPostcodeSpaced->assertJsonCount(1, 'data');

        $byPostcodeCompact = $this->agreementsDatatableResponse(['search' => ['value' => 'SW1A1AA']]);
        $byPostcodeCompact->assertOk();
        $byPostcodeCompact->assertJsonCount(1, 'data');
    }

    public function test_agreements_index_ajax_datatable_returns_expected_columns(): void
    {
        $this->createAgreement(['status_id' => $this->activeStatus->id]);

        $response = $this->getJson(route('agreements.index', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ]), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $response->assertJsonStructure([
            'draw',
            'recordsTotal',
            'recordsFiltered',
            'data' => [
                [
                    'company',
                    'driver',
                    'car',
                    'start_date',
                    'end_date',
                    'notice_date',
                    'closing_date',
                    'rent',
                    'esign_html',
                    'status_html',
                    'actions_html',
                ],
            ],
        ]);
        $response->assertJsonFragment(['company' => 'Fleet Co']);
        $response->assertJsonFragment(['car' => 'TN123']);
    }

    public function test_agreements_index_query_count_is_bounded_without_upgrade_n_plus_one(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createAgreement([
                'status_id' => $this->activeStatus->id,
                'termination_notice_date' => '2026-07-10',
            ]);
        }

        $this->createAgreement([
            'status_id' => $this->terminatedStatus->id,
            'closing_date' => '2026-06-01',
            'deposit_amount' => 250,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson(route('agreements.index', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ]), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $queryCount = count(DB::getQueryLog());

        $this->assertLessThanOrEqual(30, $queryCount, 'Expected bounded query count for agreements datatable page');
    }

    public function test_agreements_index_page_loads_without_preloading_all_agreements(): void
    {
        $this->createAgreement(['status_id' => $this->activeStatus->id]);

        $response = $this->get(route('agreements.index'));

        $response->assertOk();
        $response->assertSee('serverSide: true', false);
        $response->assertSee('agreementsHasNotice', false);
        $response->assertDontSee('data-notice-date="2026-07-10"', false);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function agreementsDatatableResponse(array $params = [])
    {
        return $this->getJson(route('agreements.index', array_merge([
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ], $params)), ['X-Requested-With' => 'XMLHttpRequest']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createAgreement(array $overrides = []): Agreement
    {
        return Agreement::query()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'driver_id' => $this->driver->id,
            'car_id' => $this->car->id,
            'start_date' => '2026-06-01',
            'end_date' => '2027-06-01',
            'agreed_rent' => 150,
            'rent_interval' => 'Weekly',
            'deposit_amount' => 200,
            'collection_type' => 'weekly',
            'status_id' => $this->activeStatus->id,
        ], $overrides));
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

        if (! Schema::hasColumn('car_insurances', 'status_id')) {
            Schema::table('car_insurances', function (Blueprint $table) {
                $table->foreignId('status_id')->nullable();
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
