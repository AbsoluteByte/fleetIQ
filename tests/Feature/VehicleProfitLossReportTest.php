<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Car;
use App\Models\CarInsurance;
use App\Models\Company;
use App\Models\InsuranceProvider;
use App\Models\Driver;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\OtherPayment;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Status;
use App\Models\Tenant;
use App\Models\User;
use App\Services\VehicleProfitLossReportService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\Concerns\SetupAgreementChangeCarDatabase;
use Tests\TestCase;

class VehicleProfitLossReportTest extends TestCase
{
    use SetupAgreementChangeCarDatabase;

    private Tenant $tenant;

    private Company $company;

    private Driver $driver;

    private Car $car;

    private Agreement $agreement;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(RoleMiddleware::class);
        $this->setUpAgreementChangeCarDatabase();
        $this->setUpHttpTestExtras();

        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));

        $this->tenant = Tenant::query()->create([
            'company_name' => 'PL Report Tenant',
            'status' => Tenant::STATUS_ACTIVE,
        ]);
        $this->company = Company::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'PL Company',
        ]);
        $this->driver = Driver::query()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'PL',
            'last_name' => 'Driver',
            'email' => 'pl-driver@example.com',
            'phone_number' => '07000000111',
        ]);

        $carModelId = (int) DB::table('car_models')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'name' => 'PL Model',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->car = Car::query()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'car_model_id' => $carModelId,
            'registration' => 'PL001',
            'purchase_date' => '2025-01-10',
            'purchase_price' => 8000,
            'fleet_status' => 'available_for_rent',
        ]);

        $activeStatus = Status::query()->create(['name' => 'Active', 'type' => 'agreement']);

        $this->agreement = Agreement::query()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'driver_id' => $this->driver->id,
            'car_id' => $this->car->id,
            'start_date' => '2026-01-01',
            'end_date' => '2027-01-01',
            'agreed_rent' => 150,
            'rent_interval' => 'Weekly',
            'deposit_amount' => 200,
            'collection_type' => 'weekly',
            'status_id' => $activeStatus->id,
        ]);

        $this->user = User::factory()->create();
        $this->user->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_primary' => true,
            'joined_at' => now(),
        ]);
        $this->assignAdminRole($this->user);
        $this->actingAs($this->user);
        $this->user->switchTenant($this->tenant->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('other_payments');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('tenant_user');
        $this->tearDownAgreementChangeCarDatabase();

        parent::tearDown();
    }

    public function test_service_reports_rental_invoiced_and_collected_and_purchase(): void
    {
        Invoice::query()->create([
            'invoice_no' => 'INV-PL-001',
            'driver_id' => $this->driver->id,
            'source_id' => $this->agreement->id,
            'invoice_type' => 'agreement',
            'invoice_date' => '2026-02-01',
            'subtotal' => 150,
            'total_amount' => 150,
            'paid_amount' => 0,
            'balance_amount' => 150,
            'status' => 'pending',
        ]);

        $invoice = Invoice::query()->create([
            'invoice_no' => 'INV-PL-002',
            'driver_id' => $this->driver->id,
            'source_id' => $this->agreement->id,
            'invoice_type' => 'agreement',
            'invoice_date' => '2026-03-01',
            'subtotal' => 100,
            'total_amount' => 100,
            'paid_amount' => 60,
            'balance_amount' => 40,
            'status' => 'partial',
        ]);

        $payment = Payment::query()->create([
            'payment_no' => 'PAY-PL-001',
            'driver_id' => $this->driver->id,
            'payment_method' => 'Cash',
            'payment_date' => '2026-03-05',
            'amount' => 60,
            'posting_status' => Payment::POSTING_STATUS_POSTED,
        ]);

        PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'allocated_amount' => 60,
            'created_at' => now(),
        ]);

        $report = app(VehicleProfitLossReportService::class)->build(
            $this->car,
            $this->tenant->id,
            null,
            null,
        );

        $summary = $report['summary'];
        $this->assertSame(250.0, $summary['rental_invoiced']);
        $this->assertSame(60.0, $summary['rental_collected']);
        $this->assertSame(8000.0, $summary['purchase']);
        $this->assertSame(60.0, $summary['total_income_collected']);
    }

    public function test_reports_index_vehicle_pl_tab_shows_summary(): void
    {
        Expense::query()->create([
            'tenant_id' => $this->tenant->id,
            'car_id' => $this->car->id,
            'type' => 'Repair',
            'date' => '2026-04-01',
            'description' => 'Brake pads',
            'amount' => 120,
            'posting_status' => Expense::POSTING_STATUS_PENDING,
        ]);

        OtherPayment::query()->create([
            'tenant_id' => $this->tenant->id,
            'other_payment_type' => OtherPayment::TYPE_VEHICLE,
            'car_id' => $this->car->id,
            'title' => 'Sale — PL001',
            'amount' => 9500,
            'payment_method' => 'Cash',
            'payment_date' => '2026-05-01',
            'posting_status' => OtherPayment::POSTING_STATUS_POSTED,
        ]);

        $response = $this->get(route('reports.index', [
            'pl_car_id' => $this->car->id,
            'pl_from' => '2026-01-01',
            'pl_to' => '2026-12-31',
        ]));

        $response->assertOk();
        $response->assertSee('Vehicle P/L', false);
        $response->assertSee('PL001', false);
        $response->assertSee('Sale income', false);
        $response->assertSee('Brake pads', false);
        $response->assertSee('Export CSV (Excel)', false);
    }

    public function test_vehicle_pl_prorates_insurance_from_yearly_premium_after_cutoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00'));

        $this->createCarInsurance('2026-10-01', '2026-10-10');

        $report = app(VehicleProfitLossReportService::class)->build($this->car, $this->tenant->id);
        $expected = round(10000 / 365 * 10, 2);

        $this->assertSame($expected, $report['summary']['insurance']);
        $this->assertSame(
            round($report['summary']['purchase'] + $expected, 2),
            round($report['summary']['total_expenses'], 2)
        );
        $insuranceLine = collect($report['lines'])->firstWhere('source_type', 'car_insurance');
        $this->assertNotNull($insuranceLine);
        $this->assertSame($expected, $insuranceLine['amount']);
        $this->assertSame('2026-10-01', $insuranceLine['date']);
        $this->assertStringContainsString('Acme Insurance — 10 days (01 Oct 2026 to 10 Oct 2026), £10,000 per year', $insuranceLine['description']);

        $pending = app(VehicleProfitLossReportService::class)->build(
            $this->car,
            $this->tenant->id,
            null,
            null,
            VehicleProfitLossReportService::POSTING_PENDING
        );
        $this->assertSame(0.0, $pending['summary']['insurance']);
    }

    public function test_vehicle_pl_insurance_ignores_days_before_october_2026(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00'));

        $this->createCarInsurance('2026-09-21', '2026-10-10');

        $report = app(VehicleProfitLossReportService::class)->build($this->car, $this->tenant->id);

        $this->assertSame(round(10000 / 365 * 10, 2), $report['summary']['insurance']);
        $insuranceLine = collect($report['lines'])->firstWhere('source_type', 'car_insurance');
        $this->assertSame('2026-10-01', $insuranceLine['date']);
        $this->assertStringContainsString('10 days (01 Oct 2026 to 10 Oct 2026)', $insuranceLine['description']);
    }

    public function test_vehicle_pl_insurance_clips_to_report_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00'));

        $this->createCarInsurance('2026-10-01', '2026-10-10');

        $report = app(VehicleProfitLossReportService::class)->build(
            $this->car,
            $this->tenant->id,
            Carbon::parse('2026-10-05'),
            Carbon::parse('2026-10-10'),
        );

        $this->assertSame(round(10000 / 365 * 6, 2), $report['summary']['insurance']);
        $insuranceLine = collect($report['lines'])->firstWhere('source_type', 'car_insurance');
        $this->assertSame('2026-10-05', $insuranceLine['date']);
        $this->assertStringContainsString('6 days (05 Oct 2026 to 10 Oct 2026)', $insuranceLine['description']);
    }

    public function test_vehicle_pl_insurance_skips_cover_that_ended_before_cutoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00'));

        $this->createCarInsurance('2026-09-01', '2026-09-20');

        $report = app(VehicleProfitLossReportService::class)->build($this->car, $this->tenant->id);

        $this->assertSame(0.0, $report['summary']['insurance']);
        $this->assertNull(collect($report['lines'])->firstWhere('source_type', 'car_insurance'));
    }

    public function test_vehicle_pl_csv_export(): void
    {
        $response = $this->get(route('reports.index', [
            'pl_car_id' => $this->car->id,
            'export' => 'pl_csv',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('purchase', strtolower($response->streamedContent()));
    }

    private function createCarInsurance(string $startDate, string $endDate): CarInsurance
    {
        $provider = InsuranceProvider::query()->create([
            'tenant_id' => $this->tenant->id,
            'provider_name' => 'Acme Insurance',
            'amount' => 10000,
        ]);

        return CarInsurance::query()->create([
            'car_id' => $this->car->id,
            'insurance_provider_id' => $provider->id,
            'start_date' => $startDate,
            'expiry_date' => $endDate,
        ]);
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

        if (! Schema::hasTable('insurance_providers')) {
            Schema::create('insurance_providers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable();
                $table->string('provider_name');
                $table->decimal('amount', 10, 2)->default(0);
                $table->date('expiry_date')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('insurance_providers') && ! Schema::hasColumn('insurance_providers', 'amount')) {
            Schema::table('insurance_providers', function (Blueprint $table) {
                $table->decimal('amount', 10, 2)->default(0);
            });
        }

        if (Schema::hasTable('car_insurances') && ! Schema::hasColumn('car_insurances', 'start_date')) {
            Schema::table('car_insurances', function (Blueprint $table) {
                $table->unsignedBigInteger('insurance_provider_id')->nullable();
                $table->date('start_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->date('canceled_date')->nullable();
                $table->unsignedBigInteger('status_id')->nullable();
            });
        }

        if (! Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('car_id')->nullable();
                $table->string('type')->nullable();
                $table->string('daily_expense_type')->nullable();
                $table->string('title')->nullable();
                $table->date('date')->nullable();
                $table->text('description')->nullable();
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('posting_status')->default('pending');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('other_payments')) {
            Schema::create('other_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id');
                $table->string('other_payment_type')->nullable();
                $table->foreignId('car_id')->nullable();
                $table->string('title')->nullable();
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('payment_method')->nullable();
                $table->date('payment_date')->nullable();
                $table->string('posting_status')->default('pending');
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
    }

    private function assignAdminRole(User $user): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);
    }
}
