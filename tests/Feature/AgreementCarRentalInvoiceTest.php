<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\AgreementCarRentalInvoice;
use App\Models\BankAccount;
use App\Models\Car;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Status;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AgreementCarRentalInvoiceService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\Concerns\SetupAgreementChangeCarDatabase;
use Tests\TestCase;

class AgreementCarRentalInvoiceTest extends TestCase
{
    use SetupAgreementChangeCarDatabase;

    private Tenant $tenant;

    private Company $company;

    private Driver $driver;

    private Car $car;

    private Agreement $agreement;

    private User $user;

    private BankAccount $bankA;

    private BankAccount $bankB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(RoleMiddleware::class);
        $this->setUpAgreementChangeCarDatabase();
        $this->setUpCarRentalInvoiceExtras();

        Carbon::setTestNow(Carbon::parse('2026-08-11 10:00:00'));

        $this->tenant = Tenant::query()->create([
            'company_name' => 'CRI Tenant',
            'status' => Tenant::STATUS_ACTIVE,
        ]);
        $this->company = Company::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Samore Traders Ltd',
            'company_registration_number' => '08741649',
        ]);
        $this->driver = Driver::query()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Besmir',
            'last_name' => 'Xhika',
            'email' => 'besmir@example.com',
            'phone_number' => '07000000999',
        ]);

        $carModelId = (int) DB::table('car_models')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'name' => 'Toyota Prius',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->car = Car::query()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'car_model_id' => $carModelId,
            'registration' => 'BN71 JCO',
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
            'start_date' => '2026-07-13',
            'end_date' => '2027-07-13',
            'agreed_rent' => 260,
            'rent_interval' => 'Weekly',
            'deposit_amount' => 200,
            'collection_type' => 'weekly',
            'status_id' => $activeStatus->id,
        ]);

        $this->bankA = BankAccount::query()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'bank_name' => 'Bank A',
            'short_name' => 'Acct A',
            'account_number' => '11111111',
        ]);
        $this->bankB = BankAccount::query()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'bank_name' => 'Bank B',
            'short_name' => 'Acct B',
            'account_number' => '22222222',
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
        Schema::dropIfExists('agreement_car_rental_invoices');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('tenant_user');
        $this->tearDownAgreementChangeCarDatabase();

        parent::tearDown();
    }

    public function test_default_selection_excludes_cash_and_respects_bank_filter(): void
    {
        [$cashId, $bankAId, $bankBId] = $this->seedRentPayments();

        $service = app(AgreementCarRentalInvoiceService::class);
        $eligible = $service->eligibleRentPayments($this->agreement);

        $default = $service->defaultSelectedPaymentIds($eligible, false, null);
        $this->assertContains($bankAId, $default);
        $this->assertContains($bankBId, $default);
        $this->assertNotContains($cashId, $default);

        $filtered = $service->defaultSelectedPaymentIds($eligible, false, $this->bankA->id);
        $this->assertSame([$bankAId], $filtered);

        $withCash = $service->defaultSelectedPaymentIds($eligible, true, null);
        $this->assertContains($cashId, $withCash);
    }

    public function test_generate_pdf_assigns_serial_and_regenerate_keeps_serial(): void
    {
        [, $bankAId, $bankBId] = $this->seedRentPayments();

        $payload = $this->validPayload([$bankAId, $bankBId]);

        $first = $this->post(route('agreements.car-rental-invoice.store', $this->agreement), $payload);
        $first->assertOk();
        $this->assertStringStartsWith('%PDF', $first->getContent());

        $record = AgreementCarRentalInvoice::query()->where('agreement_id', $this->agreement->id)->first();
        $this->assertNotNull($record);
        $this->assertSame('ST-0001', $record->serial_number);
        $this->assertSame(520.0, (float) ($record->totals_snapshot['total_paid'] ?? 0));

        $payload['weekly_insurance_amount'] = 50;
        $second = $this->post(route('agreements.car-rental-invoice.store', $this->agreement), $payload);
        $second->assertOk();

        $record->refresh();
        $this->assertSame('ST-0001', $record->serial_number);
        $this->assertSame(100.0, (float) ($record->totals_snapshot['insurance_total'] ?? 0));
    }

    public function test_show_form_displays_eligible_payments(): void
    {
        $this->seedRentPayments();

        $response = $this->get(route('agreements.car-rental-invoice.show', $this->agreement));

        $response->assertOk();
        $response->assertSee('Car rental invoice', false);
        $response->assertSee('PAY-CASH', false);
        $response->assertSee('PAY-BANK-A', false);
    }

    public function test_rejects_invalid_payment_ids(): void
    {
        $this->seedRentPayments();
        $otherTenant = Tenant::query()->create(['company_name' => 'Other', 'status' => Tenant::STATUS_ACTIVE]);
        $otherDriver = Driver::query()->create(['tenant_id' => $otherTenant->id, 'first_name' => 'X', 'last_name' => 'Y']);
        $foreignPayment = Payment::query()->create([
            'payment_no' => 'PAY-FOREIGN',
            'driver_id' => $otherDriver->id,
            'payment_method' => 'Bank Transfer',
            'bank_account_id' => $this->bankA->id,
            'payment_date' => '2026-07-20',
            'amount' => 260,
            'posting_status' => Payment::POSTING_STATUS_POSTED,
        ]);

        $payload = $this->validPayload([$foreignPayment->id]);

        $response = $this->from(route('agreements.car-rental-invoice.show', $this->agreement))
            ->post(route('agreements.car-rental-invoice.store', $this->agreement), $payload);

        $response->assertRedirect(route('agreements.car-rental-invoice.show', $this->agreement));
        $response->assertSessionHasErrors('payment_ids');
    }

    public function test_other_tenant_cannot_access_form(): void
    {
        $otherTenant = Tenant::query()->create(['company_name' => 'Other', 'status' => Tenant::STATUS_ACTIVE]);
        $otherUser = User::factory()->create();
        $otherUser->tenants()->attach($otherTenant->id, [
            'role' => 'admin',
            'is_primary' => true,
            'joined_at' => now(),
        ]);
        $this->assignAdminRole($otherUser);

        $this->actingAs($otherUser);
        $otherUser->switchTenant($otherTenant->id);

        $this->get(route('agreements.car-rental-invoice.show', $this->agreement))->assertForbidden();
    }

    /**
     * @return list<int>
     */
    private function seedRentPayments(): array
    {
        $invoices = [];
        foreach (['2026-07-13', '2026-07-20', '2026-07-27', '2026-08-03'] as $i => $date) {
            $invoices[] = Invoice::query()->create([
                'invoice_no' => 'INV-CRI-'.($i + 1),
                'driver_id' => $this->driver->id,
                'source_id' => $this->agreement->id,
                'invoice_type' => 'agreement',
                'invoice_date' => $date,
                'subtotal' => 260,
                'total_amount' => 260,
                'paid_amount' => 260,
                'balance_amount' => 0,
                'status' => 'paid',
            ]);
        }

        $cash = Payment::query()->create([
            'payment_no' => 'PAY-CASH',
            'driver_id' => $this->driver->id,
            'payment_method' => 'Cash',
            'payment_date' => '2026-07-14',
            'amount' => 260,
            'posting_status' => Payment::POSTING_STATUS_POSTED,
        ]);
        PaymentAllocation::query()->create([
            'payment_id' => $cash->id,
            'invoice_id' => $invoices[0]->id,
            'allocated_amount' => 260,
            'created_at' => now(),
        ]);

        $bankA = Payment::query()->create([
            'payment_no' => 'PAY-BANK-A',
            'driver_id' => $this->driver->id,
            'payment_method' => 'Bank Transfer',
            'bank_account_id' => $this->bankA->id,
            'payment_date' => '2026-07-21',
            'amount' => 260,
            'posting_status' => Payment::POSTING_STATUS_POSTED,
        ]);
        PaymentAllocation::query()->create([
            'payment_id' => $bankA->id,
            'invoice_id' => $invoices[1]->id,
            'allocated_amount' => 260,
            'created_at' => now(),
        ]);

        $bankB = Payment::query()->create([
            'payment_no' => 'PAY-BANK-B',
            'driver_id' => $this->driver->id,
            'payment_method' => 'Card Payment',
            'bank_account_id' => $this->bankB->id,
            'payment_date' => '2026-07-28',
            'amount' => 260,
            'posting_status' => Payment::POSTING_STATUS_POSTED,
        ]);
        PaymentAllocation::query()->create([
            'payment_id' => $bankB->id,
            'invoice_id' => $invoices[2]->id,
            'allocated_amount' => 260,
            'created_at' => now(),
        ]);

        return [(int) $cash->id, (int) $bankA->id, (int) $bankB->id];
    }

    /**
     * @param  list<int>  $paymentIds
     * @return array<string, mixed>
     */
    private function validPayload(array $paymentIds): array
    {
        return [
            'invoice_date' => '2026-08-11',
            'include_cash' => false,
            'weekly_insurance_amount' => 97,
            'vat_rate' => 20,
            'vat_registration_number' => '456 0701 06',
            'payment_ids' => $paymentIds,
        ];
    }

    private function setUpCarRentalInvoiceExtras(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedTinyInteger('status')->default(1);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('company_registration_number')->nullable();
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('company_id')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('short_name')->nullable();
            $table->string('account_number')->nullable();
            $table->timestamps();
        });

        Schema::create('agreement_car_rental_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('agreement_id');
            $table->unsignedBigInteger('company_id');
            $table->string('serial_prefix', 16);
            $table->string('serial_number', 32);
            $table->date('invoice_date');
            $table->boolean('include_cash')->default(false);
            $table->unsignedBigInteger('bank_account_filter_id')->nullable();
            $table->decimal('weekly_insurance_amount', 10, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(20);
            $table->string('vat_registration_number')->nullable();
            $table->date('rental_start_date')->nullable();
            $table->date('rental_end_date')->nullable();
            $table->json('payment_snapshot');
            $table->json('totals_snapshot');
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->timestamps();
            $table->unique('agreement_id');
        });

        Schema::create('tenant_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('user_id');
            $table->string('role')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        DB::table('roles')->insert([
            'id' => 1,
            'name' => 'admin',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assignAdminRole(User $user): void
    {
        DB::table('model_has_roles')->insert([
            'role_id' => 1,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);
    }
}
