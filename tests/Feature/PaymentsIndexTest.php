<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Car;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Status;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\SetupAgreementChangeCarDatabase;
use Tests\TestCase;

class PaymentsIndexTest extends TestCase
{
    use SetupAgreementChangeCarDatabase;

    private Tenant $tenant;

    private Company $company;

    private Status $activeAgreementStatus;

    private Status $replacementAgreementStatus;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpAgreementChangeCarDatabase();
        $this->setUpHttpTestExtras();

        $this->tenant = Tenant::query()->create([
            'company_name' => 'Payments Tenant',
            'status' => Tenant::STATUS_ACTIVE,
        ]);
        $this->company = Company::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Payments Company',
        ]);
        $this->activeAgreementStatus = Status::query()->create([
            'name' => 'Active',
            'type' => 'agreement',
        ]);
        $this->replacementAgreementStatus = Status::query()->create([
            'name' => 'Replacement Vehicle',
            'type' => 'agreement',
        ]);

        $this->user = User::factory()->create();
        $this->user->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_primary' => true,
            'joined_at' => now(),
        ]);
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => User::class,
            'model_id' => $this->user->id,
        ]);
        $this->actingAs($this->user);
        $this->user->switchTenant($this->tenant->id);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('tenant_user');
        $this->tearDownAgreementChangeCarDatabase();

        parent::tearDown();
    }

    public function test_payments_index_search_finds_driver_by_full_name_and_postcode(): void
    {
        $target = $this->createDriver('Jane', 'Paysearch', 'paysearch-target@example.com', 'SW1A 1AA');
        $other = $this->createDriver('Other', 'Driver', 'paysearch-other@example.com', 'M1 1AE');
        $this->createAgreement($target, $this->createCar('REG001'));
        $this->createAgreement($other, $this->createCar('REG002'));

        $byFullName = $this->paymentsDatatableResponse(['search' => ['value' => 'Jane Paysearch']]);
        $byFullName->assertOk();
        $byFullName->assertJsonCount(1, 'data');

        $byPostcodeCompact = $this->paymentsDatatableResponse(['search' => ['value' => 'SW1A1AA']]);
        $byPostcodeCompact->assertOk();
        $byPostcodeCompact->assertJsonCount(1, 'data');
    }

    public function test_payments_index_search_finds_driver_by_active_car_registration(): void
    {
        $driver = $this->createDriver('Search', 'ByReg', 'search-reg@example.com');
        $otherDriver = $this->createDriver('Other', 'Driver', 'other@example.com');
        $this->createAgreement($driver, $this->createCar('FINDME99'));
        $this->createAgreement($otherDriver, $this->createCar('NOTTHIS1'));

        $response = $this->paymentsDatatableResponse([
            'search' => ['value' => 'FINDME99'],
        ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['vehicle' => 'FINDME99']);
    }

    public function test_payments_index_shows_single_active_agreement_car_registration(): void
    {
        $driver = $this->createDriver('One', 'Agreement', 'one@example.com');
        $car = $this->createCar('REG111');
        $this->createAgreement($driver, $car);

        $pageResponse = $this->get(route('payments.index'));
        $pageResponse->assertOk();
        $pageResponse->assertSee('Vehicle');
        $pageResponse->assertDontSee('<th>Email</th>', false);

        $response = $this->paymentsDatatableResponse();
        $response->assertOk();
        $response->assertJsonFragment(['vehicle' => 'REG111']);
        $response->assertJsonMissing(['vehicle' => 'one@example.com']);
    }

    public function test_payments_index_shows_multiple_active_agreement_registrations_comma_separated(): void
    {
        $driver = $this->createDriver('Multi', 'Agreement', 'multi@example.com');
        $carA = $this->createCar('REGAAA');
        $carB = $this->createCar('REGBBB');
        $this->createAgreement($driver, $carA);
        $this->createAgreement($driver, $carB);

        $response = $this->paymentsDatatableResponse();
        $response->assertOk();
        $response->assertJsonFragment(['vehicle' => 'REGAAA, REGBBB']);
    }

    public function test_payments_index_shows_replacement_agreement_car_registration_with_active_agreement(): void
    {
        $driver = $this->createDriver('Replace', 'Driver', 'replace@example.com');
        $activeCar = $this->createCar('REG111');
        $replacementCar = $this->createCar('REPREPL');
        $parentAgreement = $this->createAgreement($driver, $activeCar);
        $this->createReplacementAgreement($driver, $replacementCar, $parentAgreement);

        $response = $this->paymentsDatatableResponse();
        $response->assertOk();
        $response->assertJsonFragment(['vehicle' => 'REG111, REPREPL']);
    }

    public function test_payments_index_excludes_ended_replacement_agreement_registration(): void
    {
        $driver = $this->createDriver('Ended', 'Replace', 'ended-replace@example.com');
        $activeCar = $this->createCar('REG222');
        $replacementCar = $this->createCar('OLDREPL');
        $parentAgreement = $this->createAgreement($driver, $activeCar);
        Agreement::query()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'driver_id' => $driver->id,
            'car_id' => $replacementCar->id,
            'parent_agreement_id' => $parentAgreement->id,
            'start_date' => now()->subMonths(2),
            'end_date' => now()->subMonth()->toDateString(),
            'agreed_rent' => 0,
            'rent_interval' => 'Weekly',
            'deposit_amount' => 0,
            'collection_type' => 'weekly',
            'status_id' => $this->replacementAgreementStatus->id,
        ]);

        $response = $this->paymentsDatatableResponse();
        $response->assertOk();
        $response->assertJsonFragment(['vehicle' => 'REG222']);
        $response->assertJsonMissing(['vehicle' => 'OLDREPL']);
    }

    public function test_payments_index_shows_dash_when_driver_has_no_active_agreement(): void
    {
        $driver = $this->createDriver('No', 'Agreement', 'none@example.com');
        $car = $this->createCar('REG999');
        Agreement::query()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'driver_id' => $driver->id,
            'car_id' => $car->id,
            'start_date' => now()->subMonths(2),
            'end_date' => now()->subMonth()->toDateString(),
            'agreed_rent' => 200,
            'rent_interval' => 'Weekly',
            'deposit_amount' => 300,
            'collection_type' => 'weekly',
            'status_id' => $this->activeAgreementStatus->id,
        ]);

        $response = $this->paymentsDatatableResponse();
        $response->assertOk();
        $response->assertJsonFragment(['vehicle' => '—']);
        $response->assertJsonMissing(['vehicle' => 'REG999']);
    }

    public function test_payments_index_includes_advanced_filter_panel_and_row_filter_metadata(): void
    {
        $driver = $this->createDriver('Filter', 'Meta', 'filter@example.com');
        $driver->update([
            'payment_remind_at' => '2026-07-20 14:30:00',
        ]);

        Payment::query()->create([
            'driver_id' => $driver->id,
            'payment_method' => 'Cash',
            'payment_date' => '2026-07-15',
            'amount' => 100,
            'posting_status' => Payment::POSTING_STATUS_POSTED,
            'auto_allocate' => false,
            'created_by' => $this->user->id,
        ]);

        Invoice::query()->create([
            'driver_id' => $driver->id,
            'invoice_type' => 'manual',
            'invoice_no' => 'INV-1001',
            'invoice_date' => '2026-07-10',
            'due_date' => '2026-07-17',
            'total_amount' => 200,
            'paid_amount' => 0,
            'balance_amount' => 200,
            'status' => 'pending',
        ]);

        $pageResponse = $this->get(route('payments.index'));
        $pageResponse->assertOk();
        $pageResponse->assertSee('id="paymentsFilterPanel"', false);
        $pageResponse->assertSee('id="paymentsFilterStatus"', false);
        $pageResponse->assertSee('id="paymentsReminderFrom"', false);
        $pageResponse->assertSee('id="paymentsLastPaymentFrom"', false);
        $pageResponse->assertSee('id="paymentsLatestInvoiceFrom"', false);
        $pageResponse->assertSee('Payment Due');
        $pageResponse->assertSee('Last Payment');

        $response = $this->paymentsDatatableResponse();
        $response->assertOk();
        $response->assertJsonFragment(['payment_due' => '10 Jul 2026']);
        $response->assertJsonFragment(['last_payment' => '15 Jul 2026']);

        $filteredResponse = $this->paymentsDatatableResponse([
            'filter_last_payment_from' => '2026-07-15',
            'filter_last_payment_to' => '2026-07-15',
        ]);
        $filteredResponse->assertOk();
        $filteredResponse->assertJsonPath('data.0.last_payment', '15 Jul 2026');
    }

    public function test_payments_index_sorts_by_total_due_asc_and_desc(): void
    {
        $lowerDueDriver = $this->createDriver('Miriam', 'Sort', 'miriam-sort@example.com');
        $higherDueDriver = $this->createDriver('Zara', 'Sort', 'zara-sort@example.com');

        $this->createOpenInvoice($lowerDueDriver, 'INV-LOW', 50);
        $this->createOpenInvoice($higherDueDriver, 'INV-HIGH', 200);

        $sortParams = $this->paymentsDatatableTotalDueSortParams();

        $descResponse = $this->paymentsDatatableResponse(array_merge($sortParams, [
            'order' => [
                ['column' => 8, 'dir' => 'desc'],
            ],
        ]));
        $descResponse->assertOk();
        $descResponse->assertJsonPath('data.0.total_due_html', '<strong class="text-danger">£200.00</strong>');
        $descResponse->assertJsonPath('data.1.total_due_html', '<strong class="text-danger">£50.00</strong>');

        $ascResponse = $this->paymentsDatatableResponse(array_merge($sortParams, [
            'order' => [
                ['column' => 8, 'dir' => 'asc'],
            ],
        ]));
        $ascResponse->assertOk();
        $ascResponse->assertJsonPath('data.0.total_due_html', '<strong class="text-danger">£50.00</strong>');
        $ascResponse->assertJsonPath('data.1.total_due_html', '<strong class="text-danger">£200.00</strong>');
    }

    public function test_payments_index_shows_total_due_and_credit_without_n_plus_one_queries(): void
    {
        $driver = $this->createDriver('Due', 'Driver', 'due@example.com');

        Invoice::query()->create([
            'driver_id' => $driver->id,
            'invoice_type' => 'manual',
            'invoice_no' => 'INV-2001',
            'invoice_date' => '2026-07-01',
            'due_date' => '2026-07-08',
            'total_amount' => 150,
            'paid_amount' => 0,
            'balance_amount' => 150,
            'status' => 'pending',
        ]);

        Payment::query()->create([
            'driver_id' => $driver->id,
            'payment_method' => 'Cash',
            'payment_date' => '2026-07-05',
            'amount' => 200,
            'posting_status' => Payment::POSTING_STATUS_POSTED,
            'auto_allocate' => false,
            'created_by' => $this->user->id,
        ]);

        $response = $this->paymentsDatatableResponse();
        $response->assertOk();
        $response->assertJsonFragment(['total_due_html' => '<strong class="text-danger">£150.00</strong>']);
        $response->assertJsonFragment(['credit_html' => '<strong class="text-success">£200.00</strong>']);
    }

    public function test_payments_index_shows_warning_total_due_when_pending_dfs_payment_exists(): void
    {
        $driver = $this->createDriver('Pending', 'Dfs', 'pending-dfs@example.com');

        Invoice::query()->create([
            'driver_id' => $driver->id,
            'invoice_type' => 'manual',
            'invoice_no' => 'INV-PENDING-1',
            'invoice_date' => '2026-07-01',
            'due_date' => '2026-07-08',
            'total_amount' => 150,
            'paid_amount' => 0,
            'balance_amount' => 150,
            'status' => 'pending',
        ]);

        Payment::query()->create([
            'driver_id' => $driver->id,
            'payment_method' => 'Cash',
            'payment_date' => '2026-07-05',
            'amount' => 75,
            'posting_status' => Payment::POSTING_STATUS_PENDING,
            'auto_allocate' => false,
            'created_by' => $this->user->id,
        ]);

        $response = $this->paymentsDatatableResponse();
        $response->assertOk();
        $response->assertJsonFragment(['total_due_html' => '<strong class="text-warning js-dfs-pending-amount" data-toggle="tooltip" data-placement="top" title="£75.00 pending daily financial sheet approval.">£150.00</strong>']);
    }

    public function test_payments_index_shows_paying_company_name_below_driver(): void
    {
        $driver = $this->createDriver('Paying', 'Company', 'paying@example.com');
        $car = $this->createCar('REGPAY');
        Agreement::query()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'driver_id' => $driver->id,
            'car_id' => $car->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth()->toDateString(),
            'agreed_rent' => 200,
            'rent_interval' => 'Weekly',
            'deposit_amount' => 300,
            'collection_type' => 'weekly',
            'status_id' => $this->activeAgreementStatus->id,
            'paying_company_name' => 'Metro Cars PLC',
        ]);

        $response = $this->paymentsDatatableResponse();
        $response->assertOk();
        $response->assertJsonPath('data.0.driver', fn ($driverHtml) => str_contains((string) $driverHtml, 'Pays via: Metro Cars PLC'));
    }

    public function test_payments_index_hides_paying_company_when_not_set(): void
    {
        $driver = $this->createDriver('No', 'PayingCo', 'no-paying@example.com');
        $this->createAgreement($driver, $this->createCar('REGNOPAY'));

        $response = $this->paymentsDatatableResponse();
        $response->assertOk();
        $response->assertJsonPath('data.0.driver', fn ($driverHtml) => str_contains((string) $driverHtml, 'No') && str_contains((string) $driverHtml, 'PayingCo') && ! str_contains((string) $driverHtml, 'paying-company-subtitle'));
    }

    public function test_payments_index_uses_first_active_agreement_paying_company_only(): void
    {
        $driver = $this->createDriver('First', 'Only', 'first-only@example.com');
        Agreement::query()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'driver_id' => $driver->id,
            'car_id' => $this->createCar('REGA')->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth()->toDateString(),
            'agreed_rent' => 200,
            'rent_interval' => 'Weekly',
            'deposit_amount' => 300,
            'collection_type' => 'weekly',
            'status_id' => $this->activeAgreementStatus->id,
            'paying_company_name' => 'First Company Ltd',
        ]);
        Agreement::query()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'driver_id' => $driver->id,
            'car_id' => $this->createCar('REGB')->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth()->toDateString(),
            'agreed_rent' => 200,
            'rent_interval' => 'Weekly',
            'deposit_amount' => 300,
            'collection_type' => 'weekly',
            'status_id' => $this->activeAgreementStatus->id,
            'paying_company_name' => 'Second Company Ltd',
        ]);

        $response = $this->paymentsDatatableResponse();
        $response->assertOk();
        $response->assertJsonPath('data.0.driver', fn ($driverHtml) => str_contains((string) $driverHtml, 'Pays via: First Company Ltd') && ! str_contains((string) $driverHtml, 'Second Company Ltd'));
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentsDatatableTotalDueSortParams(): array
    {
        return [
            'columns' => [
                ['data' => 'driver', 'name' => 'driver', 'searchable' => 'false', 'orderable' => 'false'],
                ['data' => 'vehicle', 'name' => 'vehicle', 'searchable' => 'false', 'orderable' => 'false'],
                ['data' => 'pay_to', 'name' => 'pay_to', 'searchable' => 'false', 'orderable' => 'false'],
                ['data' => 'phone', 'name' => 'phone', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'invoices_count', 'name' => 'invoices_count', 'searchable' => 'false', 'orderable' => 'true'],
                ['data' => 'payments_count', 'name' => 'payments_count', 'searchable' => 'false', 'orderable' => 'true'],
                ['data' => 'payment_due', 'name' => 'payment_due', 'searchable' => 'false', 'orderable' => 'false'],
                ['data' => 'last_payment', 'name' => 'last_payment', 'searchable' => 'false', 'orderable' => 'false'],
                ['data' => 'total_due_html', 'name' => 'total_due', 'searchable' => 'false', 'orderable' => 'true'],
                ['data' => 'credit_html', 'name' => 'credit_html', 'searchable' => 'false', 'orderable' => 'false'],
                ['data' => 'actions_html', 'name' => 'actions_html', 'searchable' => 'false', 'orderable' => 'false'],
            ],
        ];
    }

    private function createOpenInvoice(Driver $driver, string $invoiceNo, float $balance): Invoice
    {
        return Invoice::query()->create([
            'driver_id' => $driver->id,
            'invoice_type' => 'manual',
            'invoice_no' => $invoiceNo,
            'invoice_date' => '2026-07-01',
            'due_date' => '2026-07-08',
            'total_amount' => $balance,
            'paid_amount' => 0,
            'balance_amount' => $balance,
            'status' => 'pending',
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function paymentsDatatableResponse(array $params = [])
    {
        return $this->call(
            'GET',
            route('payments.index'),
            array_merge([
                'draw' => 1,
                'start' => 0,
                'length' => 25,
            ], $params),
            [],
            [],
            [
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ]
        );
    }

    private function setUpHttpTestExtras(): void
    {
        if (! Schema::hasColumn('tenants', 'status')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->unsignedTinyInteger('status')->default(1);
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
                $table->id();
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
            });
        }

        if (! Schema::hasColumn('drivers', 'payment_remind_at')) {
            Schema::table('drivers', function (Blueprint $table) {
                $table->dateTime('payment_remind_at')->nullable();
            });
        }

        if (! Schema::hasColumn('drivers', 'payment_follow_up_notes')) {
            Schema::table('drivers', function (Blueprint $table) {
                $table->text('payment_follow_up_notes')->nullable();
            });
        }

        if (! Schema::hasColumn('agreements', 'parent_agreement_id')) {
            Schema::table('agreements', function (Blueprint $table) {
                $table->unsignedBigInteger('parent_agreement_id')->nullable();
            });
        }

        if (! Schema::hasColumn('agreements', 'paying_company_name')) {
            Schema::table('agreements', function (Blueprint $table) {
                $table->string('paying_company_name')->nullable();
            });
        }
    }

    private function createDriver(string $firstName, string $lastName, string $email, ?string $postCode = null): Driver
    {
        return Driver::query()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'post_code' => $postCode,
            'is_active' => true,
        ]);
    }

    private function createCar(string $registration): Car
    {
        $carModelId = (int) DB::table('car_models')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'name' => 'Model',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Car::query()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'car_model_id' => $carModelId,
            'registration' => $registration,
            'color' => 'Black',
            'fleet_status' => Car::FLEET_STATUS_AVAILABLE_FOR_RENT,
        ]);
    }

    private function createAgreement(Driver $driver, Car $car): Agreement
    {
        return Agreement::query()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'driver_id' => $driver->id,
            'car_id' => $car->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth()->toDateString(),
            'agreed_rent' => 200,
            'rent_interval' => 'Weekly',
            'deposit_amount' => 300,
            'collection_type' => 'weekly',
            'status_id' => $this->activeAgreementStatus->id,
        ]);
    }

    private function createReplacementAgreement(Driver $driver, Car $car, Agreement $parentAgreement): Agreement
    {
        return Agreement::query()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'driver_id' => $driver->id,
            'car_id' => $car->id,
            'parent_agreement_id' => $parentAgreement->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth()->toDateString(),
            'agreed_rent' => 0,
            'rent_interval' => 'Weekly',
            'deposit_amount' => 0,
            'collection_type' => 'weekly',
            'status_id' => $this->replacementAgreementStatus->id,
        ]);
    }
}
