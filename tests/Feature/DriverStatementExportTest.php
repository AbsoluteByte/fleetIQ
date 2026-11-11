<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\SetupAgreementChangeCarDatabase;
use Tests\TestCase;

class DriverStatementExportTest extends TestCase
{
    use SetupAgreementChangeCarDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpAgreementChangeCarDatabase();
        $this->setUpHttpTestExtras();

        $this->tenant = Tenant::query()->create([
            'company_name' => 'Export Tenant',
            'status' => Tenant::STATUS_ACTIVE,
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

    public function test_driver_invoice_and_payment_exports_download_and_block_other_tenants(): void
    {
        $driver = Driver::query()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Akhtar',
            'last_name' => 'Uddin',
            'email' => 'akhtar-export@example.com',
            'is_active' => true,
        ]);

        Invoice::query()->create([
            'invoice_no' => 'INV-EXPORT-100',
            'driver_id' => $driver->id,
            'invoice_type' => 'agreement',
            'invoice_date' => '2026-11-01',
            'due_date' => '2026-11-03',
            'subtotal' => 120,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 120,
            'paid_amount' => 20,
            'balance_amount' => 100,
            'status' => 'partial',
            'notes' => 'November rent',
        ]);

        Payment::query()->create([
            'payment_no' => 'PAY-EXPORT-100',
            'driver_id' => $driver->id,
            'payment_method' => 'Cash',
            'payment_date' => '2026-11-02',
            'amount' => 20,
            'notes' => 'Cash received',
            'posting_status' => Payment::POSTING_STATUS_POSTED,
        ]);

        $invoiceCsv = $this->get(route('payments.driver.invoices.export', [$driver, 'csv']));
        $invoiceCsv->assertOk();
        $this->assertStringContainsString('text/csv', (string) $invoiceCsv->headers->get('content-type'));
        $invoiceCsvBody = $invoiceCsv->streamedContent();
        $this->assertStringContainsString('INV-EXPORT-100', $invoiceCsvBody);
        $this->assertStringContainsString('120.00', $invoiceCsvBody);
        $this->assertStringContainsString('Akhtar_Uddin_invoices.csv', (string) $invoiceCsv->headers->get('content-disposition'));

        $paymentCsv = $this->get(route('payments.driver.payments.export', [$driver, 'csv']));
        $paymentCsv->assertOk();
        $paymentCsvBody = $paymentCsv->streamedContent();
        $this->assertStringContainsString('PAY-EXPORT-100', $paymentCsvBody);
        $this->assertStringContainsString('20.00', $paymentCsvBody);

        $invoicePdf = $this->get(route('payments.driver.invoices.export', [$driver, 'pdf']));
        $invoicePdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $invoicePdf->headers->get('content-type'));

        $paymentPdf = $this->get(route('payments.driver.payments.export', [$driver, 'pdf']));
        $paymentPdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $paymentPdf->headers->get('content-type'));

        $otherTenant = Tenant::query()->create([
            'company_name' => 'Other Export Tenant',
            'status' => Tenant::STATUS_ACTIVE,
        ]);
        $otherDriver = Driver::query()->create([
            'tenant_id' => $otherTenant->id,
            'first_name' => 'Other',
            'last_name' => 'Driver',
            'email' => 'other-export@example.com',
            'is_active' => true,
        ]);

        $this->get(route('payments.driver.invoices.export', [$otherDriver, 'csv']))->assertForbidden();
        $this->get(route('payments.driver.payments.export', [$otherDriver, 'pdf']))->assertForbidden();
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
    }
};
