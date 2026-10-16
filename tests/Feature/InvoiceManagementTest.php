<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\TestCase;

class InvoiceManagementTest extends TestCase
{
    private Tenant $tenant;

    private User $manager;

    private User $employee;

    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(RoleMiddleware::class);
        $this->setUpSchema();

        $this->tenant = Tenant::query()->create([
            'company_name' => 'Invoice Tenant',
            'status' => Tenant::STATUS_ACTIVE,
        ]);
        $this->driver = Driver::query()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'email' => 'ali@example.com',
        ]);

        $this->manager = User::factory()->create(['email' => 'jawad@samoretraders.com']);
        $this->employee = User::factory()->create(['email' => 'staff@example.com']);
        $this->manager->tenants()->attach($this->tenant->id, ['role' => 'admin', 'is_primary' => true, 'joined_at' => now()]);
        $this->employee->tenants()->attach($this->tenant->id, ['role' => 'admin', 'is_primary' => true, 'joined_at' => now()]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('daily_financial_sheets');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('users');
        Schema::dropIfExists('tenants');

        parent::tearDown();
    }

    public function test_jawad_can_update_invoice_total(): void
    {
        $invoice = $this->createInvoice(200);

        $this->actingAs($this->manager);
        $this->manager->switchTenant($this->tenant->id);

        $response = $this->patch(route('invoices.update', $invoice), [
            'subtotal' => 180,
            'total_amount' => 150,
        ]);

        $response->assertRedirect();
        $invoice->refresh();
        $this->assertEquals(150, (float) $invoice->total_amount);
        $this->assertEquals(150, (float) $invoice->balance_amount);
    }

    public function test_non_manager_cannot_update_invoice(): void
    {
        $invoice = $this->createInvoice(200);

        $this->actingAs($this->employee);
        $this->employee->switchTenant($this->tenant->id);

        $this->patch(route('invoices.update', $invoice), [
            'subtotal' => 180,
            'total_amount' => 150,
        ])->assertForbidden();
    }

    public function test_jawad_can_delete_unallocated_invoice(): void
    {
        $invoice = $this->createInvoice(200);

        $this->actingAs($this->manager);
        $this->manager->switchTenant($this->tenant->id);

        $this->delete(route('invoices.destroy', $invoice))
            ->assertRedirect();

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    }

    public function test_deleting_an_allocated_invoice_without_a_choice_does_not_delete_it(): void
    {
        $invoice = $this->createInvoice(200);
        $payment = $this->createPayment(50);
        PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'allocated_amount' => 50,
        ]);

        $this->actingAs($this->manager);
        $this->manager->switchTenant($this->tenant->id);

        $this->delete(route('invoices.destroy', $invoice))
            ->assertRedirect()
            ->assertSessionHas('error', 'Choose whether to delete the payment or add it to driver credit.');

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }

    public function test_deleting_a_paid_invoice_as_credit_keeps_the_payment(): void
    {
        $invoice = $this->createInvoice(100);
        $payment = $this->createPayment(100);
        PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'allocated_amount' => 100,
        ]);

        $this->actingAs($this->manager);
        $this->manager->switchTenant($this->tenant->id);

        $this->delete(route('invoices.destroy', $invoice), [
            'payment_disposition' => 'credit',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'amount' => 100]);
        $this->assertDatabaseMissing('payment_allocations', ['invoice_id' => $invoice->id]);
        $this->assertEquals(100.0, (float) $this->driver->fresh()->credit_amount);
    }

    public function test_deleting_a_paid_invoice_can_remove_the_payment(): void
    {
        $invoice = $this->createInvoice(100);
        $payment = $this->createPayment(100);
        PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'allocated_amount' => 100,
        ]);

        $this->actingAs($this->manager);
        $this->manager->switchTenant($this->tenant->id);

        $this->delete(route('invoices.destroy', $invoice), [
            'payment_disposition' => 'delete',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
        $this->assertEquals(0.0, (float) $this->driver->fresh()->credit_amount);
    }

    public function test_deleting_one_invoice_only_removes_that_share_of_a_split_payment(): void
    {
        $removed = $this->createInvoice(40);
        $kept = $this->createInvoice(60);
        $payment = $this->createPayment(100);
        PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $removed->id,
            'allocated_amount' => 40,
        ]);
        PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $kept->id,
            'allocated_amount' => 60,
        ]);

        $this->actingAs($this->manager);
        $this->manager->switchTenant($this->tenant->id);

        $this->delete(route('invoices.destroy', $removed), [
            'payment_disposition' => 'delete',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseMissing('invoices', ['id' => $removed->id]);
        $this->assertDatabaseHas('invoices', ['id' => $kept->id]);
        $this->assertEquals(60.0, (float) $payment->fresh()->amount);
        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $payment->id,
            'invoice_id' => $kept->id,
            'allocated_amount' => 60,
        ]);
        $this->assertDatabaseMissing('payment_allocations', ['invoice_id' => $removed->id]);
        $this->assertEquals(0.0, (float) $this->driver->fresh()->credit_amount);
    }

    public function test_paid_invoices_show_the_payment_choice_on_both_invoice_tables(): void
    {
        $unpaid = $this->createInvoice(80);
        $unpaid->forceFill(['source_id' => null])->save();

        $paid = $this->createInvoice(50);
        $paid->forceFill([
            'source_id' => null,
            'paid_amount' => 50,
            'balance_amount' => 0,
            'status' => 'paid',
            'invoice_no' => 'INV-PAID',
        ])->save();
        $payment = $this->createPayment(50);
        PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $paid->id,
            'allocated_amount' => 50,
        ]);

        $invoices = Invoice::query()->with('paymentAllocations')->orderBy('id')->get();
        $paymentsTable = view('backend.payments.partials.invoices-table', [
            'invoices' => $invoices,
            'canManageInvoices' => true,
        ])->render();

        $this->assertStringContainsString("confirm('Delete this invoice?');", $paymentsTable);
        $this->assertStringContainsString('js-delete-paid-invoice', $paymentsTable);
        $this->assertStringContainsString('data-invoice-no="INV-PAID"', $paymentsTable);
        $this->assertStringContainsString('data-paid-amount="50.00"', $paymentsTable);
        $this->assertStringContainsString('Add to driver credit', $paymentsTable);
        $this->assertStringContainsString('Delete the payment too', $paymentsTable);
        $this->assertStringContainsString('name="payment_disposition" value="credit"', $paymentsTable);
        $this->assertStringContainsString('name="payment_disposition" value="delete"', $paymentsTable);

        $agreement = new Agreement;
        $agreement->driver_id = $this->driver->id;
        $agreement->setRelation('invoices', $invoices);
        $agreementTable = view('backend.agreements.partials.invoices', [
            'agreement' => $agreement,
            'canManageInvoices' => true,
        ])->render();

        $this->assertStringContainsString("confirm('Delete this invoice?');", $agreementTable);
        $this->assertStringContainsString('js-delete-paid-invoice', $agreementTable);
        $this->assertStringContainsString('data-invoice-no="INV-PAID"', $agreementTable);
    }

    private function createInvoice(float $total): Invoice
    {
        return Invoice::query()->create([
            'driver_id' => $this->driver->id,
            'invoice_type' => 'agreement',
            'source_id' => 1,
            'invoice_date' => '2026-06-15',
            'due_date' => '2026-06-15',
            'subtotal' => $total,
            'total_amount' => $total,
            'paid_amount' => 0,
            'balance_amount' => $total,
            'status' => 'pending',
        ]);
    }

    private function createPayment(float $amount): Payment
    {
        return Payment::query()->create([
            'driver_id' => $this->driver->id,
            'payment_method' => 'Cash',
            'payment_date' => '2026-06-15',
            'amount' => $amount,
            'posting_status' => Payment::POSTING_STATUS_POSTED,
        ]);
    }

    private function setUpSchema(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('tenant_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('user_id');
            $table->string('role')->default('admin');
            $table->boolean('is_primary')->default(true);
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
        });

        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no')->nullable();
            $table->foreignId('driver_id');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('invoice_type');
            $table->date('invoice_date')->nullable();
            $table->dateTime('due_date')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance_amount', 12, 2)->default(0);
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no')->nullable();
            $table->unsignedBigInteger('driver_id');
            $table->string('payment_method')->nullable();
            $table->date('payment_date')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('posting_status')->default('pending');
            $table->boolean('exclude_from_daily_financial')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('daily_financial_sheets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->date('sheet_date');
            $table->string('status')->default('open');
            $table->timestamps();
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id');
            $table->foreignId('invoice_id');
            $table->decimal('allocated_amount', 12, 2);
            $table->timestamps();
        });
    }
}
