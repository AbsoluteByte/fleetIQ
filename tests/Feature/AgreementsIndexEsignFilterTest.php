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

class AgreementsIndexEsignFilterTest extends TestCase
{
    use SetupAgreementChangeCarDatabase;

    private Tenant $tenant;

    private Company $company;

    private Driver $driver;

    private int $carModelId;

    private int $counselId;

    private Status $activeStatus;

    private Status $swapStatus;

    private Status $replacementStatus;

    private Status $terminatedStatus;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(RoleMiddleware::class);
        $this->setUpAgreementChangeCarDatabase();
        $this->setUpHttpTestExtras();

        $this->tenant = Tenant::query()->create([
            'company_name' => 'Agreements Esign Tenant',
            'status' => Tenant::STATUS_ACTIVE,
        ]);
        $this->company = Company::query()->create(['tenant_id' => $this->tenant->id, 'name' => 'Fleet Co']);
        $this->driver = Driver::query()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Sam',
            'last_name' => 'Driver',
            'email' => 'sam-esign-agreements@example.com',
            'phone_number' => '07000000008',
        ]);

        $this->carModelId = (int) DB::table('car_models')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Model',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->counselId = (int) DB::table('counsels')->insertGetId([
            'name' => 'Test Counsel',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->activeStatus = Status::query()->create(['name' => 'Active', 'type' => 'agreement']);
        $this->swapStatus = Status::query()->create(['name' => 'Swap', 'type' => 'agreement']);
        $this->replacementStatus = Status::query()->create(['name' => 'Replacement Vehicle', 'type' => 'agreement']);
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
        Schema::dropIfExists('agreement_signature_tokens');
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

    public function test_esign_filter_keeps_live_signed_and_unsigned_agreements(): void
    {
        $page = $this->get(route('agreements.index'));
        $page->assertOk();
        $page->assertSee('id="agreementsFilterEsign"', false);
        $page->assertSee('E-signed', false);
        $page->assertSee('Not signed', false);
        $page->assertSee('Active, Swap, and Replacement Vehicle only.', false);

        $activeSigned = $this->createAgreement($this->activeStatus, '2026-01-01', 'signed');
        $activeUnsigned = $this->createAgreement($this->activeStatus, '2026-02-01', null);
        $swapSigned = $this->createAgreement($this->swapStatus, '2026-03-01', 'signed');
        $replacementUnsigned = $this->createAgreement($this->replacementStatus, '2026-04-01', 'pending');
        $terminatedSigned = $this->createAgreement($this->terminatedStatus, '2026-05-01', 'signed');
        $tokenSigned = $this->createAgreement($this->activeStatus, '2026-06-01', null);
        DB::table('agreement_signature_tokens')->insert([
            'agreement_id' => $tokenSigned->id,
            'token' => 'signed-filter-token',
            'signer_email' => 'sam-esign-agreements@example.com',
            'signer_name' => 'Sam Driver',
            'status' => 'signed',
            'signature_data' => 'data:image/png;base64,abc',
            'signed_at' => '2026-03-03 14:30:00',
            'expires_at' => '2026-09-01 00:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $signed = $this->datatable(['filter_esign' => 'signed']);
        $signed->assertOk();
        $signed->assertJsonCount(3, 'data');
        $signed->assertJsonFragment(['start_date' => 'Jan 01, 2026']);
        $signed->assertJsonFragment(['start_date' => 'Mar 01, 2026']);
        $signed->assertJsonFragment(['start_date' => 'Jun 01, 2026']);
        $signed->assertJsonMissing(['start_date' => 'Feb 01, 2026']);
        $signed->assertJsonMissing(['start_date' => 'Apr 01, 2026']);
        $signed->assertJsonMissing(['start_date' => 'May 01, 2026']);

        $unsigned = $this->datatable(['filter_esign' => 'unsigned']);
        $unsigned->assertOk();
        $unsigned->assertJsonCount(2, 'data');
        $unsigned->assertJsonFragment(['start_date' => 'Feb 01, 2026']);
        $unsigned->assertJsonFragment(['start_date' => 'Apr 01, 2026']);
        $unsigned->assertJsonMissing(['start_date' => 'Jan 01, 2026']);
        $unsigned->assertJsonMissing(['start_date' => 'Mar 01, 2026']);
        $unsigned->assertJsonMissing(['start_date' => 'May 01, 2026']);
        $unsigned->assertJsonMissing(['start_date' => 'Jun 01, 2026']);

        $this->assertNotNull($activeSigned->id);
        $this->assertNotNull($activeUnsigned->id);
        $this->assertNotNull($swapSigned->id);
        $this->assertNotNull($replacementUnsigned->id);
        $this->assertNotNull($terminatedSigned->id);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function datatable(array $params)
    {
        return $this->getJson(route('agreements.index', array_merge([
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ], $params)), ['X-Requested-With' => 'XMLHttpRequest']);
    }

    private function createAgreement(Status $status, string $startDate, ?string $esignStatus): Agreement
    {
        $car = $this->createCar('ES'.substr(md5($startDate.$status->name), 0, 5));

        return Agreement::query()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'driver_id' => $this->driver->id,
            'car_id' => $car->id,
            'start_date' => $startDate,
            'end_date' => '2027-06-01',
            'agreed_rent' => 150,
            'rent_interval' => 'Weekly',
            'deposit_amount' => 200,
            'collection_type' => 'weekly',
            'status_id' => $status->id,
            'hellosign_status' => $esignStatus,
        ]);
    }

    private function setUpHttpTestExtras(): void
    {
        if (! Schema::hasColumn('tenants', 'status')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->unsignedTinyInteger('status')->default(1);
            });
        }

        if (! Schema::hasColumn('agreements', 'hellosign_status')) {
            Schema::table('agreements', function (Blueprint $table) {
                $table->string('hellosign_status')->nullable();
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

        if (! Schema::hasTable('agreement_signature_tokens')) {
            Schema::create('agreement_signature_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agreement_id');
                $table->string('token', 100)->unique();
                $table->string('signer_email');
                $table->string('signer_name');
                $table->string('status')->default('pending');
                $table->longText('signature_data')->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->timestamp('expires_at');
                $table->timestamps();
            });
        }
    }

    private function createCar(string $registration): Car
    {
        $car = Car::query()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'car_model_id' => $this->carModelId,
            'registration' => $registration,
            'color' => 'Black',
            'vin' => 'VIN'.$registration,
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
            'counsel_id' => $this->counselId,
            'amount' => 200,
            'start_date' => '2026-01-01',
            'expiry_date' => '2027-01-01',
            'notify_before_expiry' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $car;
    }
};
