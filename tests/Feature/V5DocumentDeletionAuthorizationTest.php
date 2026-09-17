<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use App\Services\V5DocumentAuthorizationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\TestCase;

class V5DocumentDeletionAuthorizationTest extends TestCase
{
    private Tenant $tenant;

    private Company $company;

    private int $carModelId;

    private User $jawad;

    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(RoleMiddleware::class);
        $this->setUpDatabase();

        $this->tenant = Tenant::create([
            'company_name' => 'V5 Auth Test Tenant',
            'status' => Tenant::STATUS_ACTIVE,
        ]);

        $this->company = Company::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'V5 Auth Test Company',
        ]);

        $this->carModelId = (int) DB::table('car_models')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Model',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->jawad = User::factory()->create([
            'email' => V5DocumentAuthorizationService::V5_DELETE_ALLOWED_EMAIL,
        ]);
        $this->otherUser = User::factory()->create([
            'email' => 'other.user@example.com',
        ]);

        foreach ([$this->jawad, $this->otherUser] as $user) {
            $user->tenants()->attach($this->tenant->id, [
                'role' => 'admin',
                'is_primary' => true,
                'joined_at' => now(),
            ]);
        }
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('car_phvl_progress_events');
        Schema::dropIfExists('car_phvl_archives');
        Schema::dropIfExists('car_phvl_progress');
        Schema::dropIfExists('vehicle_swaps');
        Schema::dropIfExists('car_status_histories');
        Schema::dropIfExists('car_sorn_histories');
        Schema::dropIfExists('car_reservations');
        Schema::dropIfExists('car_services');
        Schema::dropIfExists('car_insurances');
        Schema::dropIfExists('car_phvs');
        Schema::dropIfExists('car_road_taxes');
        Schema::dropIfExists('car_mots');
        Schema::dropIfExists('cars');
        Schema::dropIfExists('car_models');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('tenant_user');
        Schema::dropIfExists('users');
        Schema::dropIfExists('tenants');

        parent::tearDown();
    }

    public function test_jawad_can_remove_v5_document(): void
    {
        $car = $this->createCar(['v5-logbook.pdf'], 'V5J001');
        $this->actingAs($this->jawad);
        $this->jawad->switchTenant($this->tenant->id);

        $response = $this->deleteJson(route('cars.v5-document.destroy', [$car, 0]));

        $response->assertOk();
        $response->assertJson(['ok' => true]);
        $car->refresh();
        $this->assertSame([], $car->v5DocumentFileNames());
    }

    public function test_non_jawad_cannot_remove_v5_document(): void
    {
        $car = $this->createCar(['v5-logbook.pdf'], 'V5J002');
        $this->actingAs($this->otherUser);
        $this->otherUser->switchTenant($this->tenant->id);

        $response = $this->deleteJson(route('cars.v5-document.destroy', [$car, 0]));

        $response->assertForbidden();
        $response->assertJson([
            'ok' => false,
            'message' => 'Only Jawad is authorised to delete V5 documents.',
        ]);
        $car->refresh();
        $this->assertSame(['v5-logbook.pdf'], $car->v5DocumentFileNames());
    }

    public function test_non_jawad_cannot_delete_car_with_v5_documents(): void
    {
        $car = $this->createCar(['v5-logbook.pdf'], 'V5J003');
        $this->actingAs($this->otherUser);
        $this->otherUser->switchTenant($this->tenant->id);

        $response = $this->delete(route('cars.destroy', $car));

        $response->assertRedirect(route('cars.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('cars', ['id' => $car->id]);
    }

    public function test_non_jawad_can_delete_car_without_v5_documents(): void
    {
        $car = $this->createCar(null, 'V5J004');
        $this->actingAs($this->otherUser);
        $this->otherUser->switchTenant($this->tenant->id);

        $response = $this->delete(route('cars.destroy', $car));

        $response->assertRedirect(route('cars.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('cars', ['id' => $car->id]);
    }

    public function test_jawad_can_delete_car_with_v5_documents(): void
    {
        $car = $this->createCar(['v5-logbook.pdf'], 'V5J005');
        $this->actingAs($this->jawad);
        $this->jawad->switchTenant($this->tenant->id);

        $response = $this->delete(route('cars.destroy', $car));

        $response->assertRedirect(route('cars.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('cars', ['id' => $car->id]);
    }

    /**
     * @param  list<string>|null  $v5Files
     */
    private function createCar(?array $v5Files, string $registration): Car
    {
        return Car::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'car_model_id' => $this->carModelId,
            'registration' => $registration,
            'v5_document' => $v5Files,
            'fleet_status' => 'available_for_rent',
            'createdBy' => $this->jawad->id,
            'updatedBy' => $this->jawad->id,
        ]);
    }

    private function setUpDatabase(): void
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

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('car_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable();
            $table->foreignId('company_id');
            $table->foreignId('car_model_id');
            $table->string('registration')->unique();
            $table->string('fleet_status')->default('available_for_rent');
            $table->json('v5_document')->nullable();
            $table->json('old_log_book')->nullable();
            $table->unsignedBigInteger('createdBy')->nullable();
            $table->unsignedBigInteger('updatedBy')->nullable();
            $table->timestamps();
        });

        Schema::create('car_mots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id');
            $table->timestamps();
        });

        Schema::create('car_road_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id');
            $table->timestamps();
        });

        Schema::create('car_phvs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id');
            $table->timestamps();
        });

        Schema::create('car_insurances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id');
            $table->timestamps();
        });

        Schema::create('car_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id');
            $table->timestamps();
        });

        Schema::create('car_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('vehicle_swaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable();
            $table->foreignId('old_car_id')->nullable();
            $table->foreignId('swapped_with_car_id')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('car_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id');
            $table->timestamps();
        });

        Schema::create('car_sorn_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id');
            $table->timestamps();
        });

        Schema::create('car_phvl_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable();
            $table->foreignId('car_id');
            $table->timestamps();
        });

        Schema::create('car_phvl_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id');
            $table->timestamps();
        });

        Schema::create('car_phvl_progress_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id');
            $table->timestamps();
        });
    }
}
