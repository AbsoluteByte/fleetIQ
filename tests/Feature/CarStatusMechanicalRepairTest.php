<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\CarStatusHistory;
use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\Concerns\SetupPhvlManagementDatabase;
use Tests\TestCase;

class CarStatusMechanicalRepairTest extends TestCase
{
    use SetupPhvlManagementDatabase;

    private Tenant $tenant;

    private Company $company;

    private int $carModelId;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(RoleMiddleware::class);
        $this->setUpPhvlManagementDatabase();

        $this->tenant = Tenant::create([
            'company_name' => 'Mechanical Repair Test Tenant',
            'status' => Tenant::STATUS_ACTIVE,
        ]);

        $this->company = Company::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Mechanical Repair Test Company',
        ]);

        $this->carModelId = (int) DB::table('car_models')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Model',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
        $this->tearDownPhvlManagementDatabase();

        parent::tearDown();
    }

    public function test_store_applies_mechanical_repair_and_records_intake_fields(): void
    {
        $car = $this->createCar('MECH001', Car::FLEET_STATUS_AVAILABLE_FOR_RENT);

        $response = $this->post(route('car-status.store'), [
            'car_id' => $car->id,
            'target_status' => Car::FLEET_STATUS_MECHANICAL_REPAIR,
            'payload' => $this->mechanicalIntakePayload(),
        ]);

        $response->assertRedirect(route('cars.show', $car));

        $car->refresh();
        $this->assertSame(Car::FLEET_STATUS_MECHANICAL_REPAIR, $car->fleet_status);

        $history = CarStatusHistory::query()
            ->where('car_id', $car->id)
            ->where('new_status', Car::FLEET_STATUS_MECHANICAL_REPAIR)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('2026-07-01', $history->status_data['issue_reported_date']);
        $this->assertSame('Brake failure', $history->status_data['issue_details']);
        $this->assertSame('2026-07-02', $history->status_data['allocation_date']);
        $this->assertSame('Sam Mechanic', $history->status_data['allocated_to']);
    }

    public function test_reservation_is_blocked_for_car_in_mechanical_repair(): void
    {
        $car = $this->createCar('MECH002', Car::FLEET_STATUS_MECHANICAL_REPAIR);

        $response = $this->from(route('reservations.create'))
            ->post(route('reservations.store'), [
                'driver_mode' => 'new',
                'first_name' => 'Ali',
                'car_id' => $car->id,
                'reservation_date' => '2026-07-10',
                'pick_up_date' => '2026-07-15',
                'agreed_rent' => 100,
                'agreed_advance' => 0,
                'amount_paid' => 0,
            ]);

        $response->assertRedirect(route('reservations.create'));
        $response->assertSessionHasErrors('car_id');
    }

    public function test_leaving_mechanical_repair_requires_completion_fields(): void
    {
        $car = $this->createCar('MECH003', Car::FLEET_STATUS_MECHANICAL_REPAIR);
        CarStatusHistory::create([
            'tenant_id' => $this->tenant->id,
            'car_id' => $car->id,
            'previous_status' => Car::FLEET_STATUS_AVAILABLE_FOR_RENT,
            'new_status' => Car::FLEET_STATUS_MECHANICAL_REPAIR,
            'status_data' => $this->mechanicalIntakePayload(),
            'changed_by' => $this->user->id,
        ]);

        $response = $this->from(route('car-status.create'))
            ->post(route('car-status.store'), [
                'car_id' => $car->id,
                'target_status' => Car::FLEET_STATUS_AVAILABLE_FOR_RENT,
            ]);

        $response->assertRedirect(route('car-status.create'));
        $response->assertSessionHasErrors(['payload.completed_date', 'payload.completion_notes']);
    }

    public function test_leaving_mechanical_repair_closes_episode_and_creates_new_history(): void
    {
        $car = $this->createCar('MECH004', Car::FLEET_STATUS_MECHANICAL_REPAIR);
        $episode = CarStatusHistory::create([
            'tenant_id' => $this->tenant->id,
            'car_id' => $car->id,
            'previous_status' => Car::FLEET_STATUS_AVAILABLE_FOR_RENT,
            'new_status' => Car::FLEET_STATUS_MECHANICAL_REPAIR,
            'status_data' => $this->mechanicalIntakePayload(),
            'changed_by' => $this->user->id,
        ]);

        $response = $this->post(route('car-status.store'), [
            'car_id' => $car->id,
            'target_status' => Car::FLEET_STATUS_AVAILABLE_FOR_RENT,
            'payload' => [
                'completed_date' => '2026-07-20',
                'completion_notes' => 'Brakes replaced; ready for rent.',
                'repair_progress_notes' => 'Pads and discs done.',
            ],
        ]);

        $response->assertRedirect(route('cars.show', $car));

        $car->refresh();
        $this->assertSame(Car::FLEET_STATUS_AVAILABLE_FOR_RENT, $car->fleet_status);

        $episode->refresh();
        $this->assertSame('2026-07-20', $episode->status_data['completed_date']);
        $this->assertSame('Brakes replaced; ready for rent.', $episode->status_data['completion_notes']);
        $this->assertSame('Pads and discs done.', $episode->status_data['repair_progress_notes']);
        $this->assertSame('Brake failure', $episode->status_data['issue_details']);

        $this->assertDatabaseHas('car_status_histories', [
            'car_id' => $car->id,
            'previous_status' => Car::FLEET_STATUS_MECHANICAL_REPAIR,
            'new_status' => Car::FLEET_STATUS_AVAILABLE_FOR_RENT,
        ]);
    }

    public function test_update_current_mechanical_repair_persists_status_data(): void
    {
        $car = $this->createCar('MECH005', Car::FLEET_STATUS_MECHANICAL_REPAIR);
        $history = CarStatusHistory::create([
            'tenant_id' => $this->tenant->id,
            'car_id' => $car->id,
            'previous_status' => Car::FLEET_STATUS_AVAILABLE_FOR_RENT,
            'new_status' => Car::FLEET_STATUS_MECHANICAL_REPAIR,
            'status_data' => $this->mechanicalIntakePayload(),
            'changed_by' => $this->user->id,
        ]);

        $response = $this->put(route('car-status.current.update', $car), [
            'edit_current_status' => 1,
            'target_status' => Car::FLEET_STATUS_MECHANICAL_REPAIR,
            'payload' => array_merge($this->mechanicalIntakePayload(), [
                'repair_progress_notes' => 'Waiting for parts.',
                'completed_date' => '2026-07-25',
                'completion_notes' => 'Pre-filled before exit.',
            ]),
        ]);

        $response->assertRedirect(route('cars.show', $car));

        $history->refresh();
        $this->assertSame('Waiting for parts.', $history->status_data['repair_progress_notes']);
        $this->assertSame('2026-07-25', $history->status_data['completed_date']);
        $this->assertSame('Pre-filled before exit.', $history->status_data['completion_notes']);
    }

    public function test_second_mechanical_repair_episode_creates_separate_history_rows(): void
    {
        $car = $this->createCar('MECH006', Car::FLEET_STATUS_AVAILABLE_FOR_RENT);

        $this->post(route('car-status.store'), [
            'car_id' => $car->id,
            'target_status' => Car::FLEET_STATUS_MECHANICAL_REPAIR,
            'payload' => $this->mechanicalIntakePayload(),
        ]);

        $firstEpisode = CarStatusHistory::query()
            ->where('car_id', $car->id)
            ->where('new_status', Car::FLEET_STATUS_MECHANICAL_REPAIR)
            ->oldest('id')
            ->firstOrFail();

        $this->post(route('car-status.store'), [
            'car_id' => $car->id,
            'target_status' => Car::FLEET_STATUS_AVAILABLE_FOR_RENT,
            'payload' => [
                'completed_date' => '2026-07-20',
                'completion_notes' => 'Done.',
            ],
        ]);

        $this->post(route('car-status.store'), [
            'car_id' => $car->id,
            'target_status' => Car::FLEET_STATUS_MECHANICAL_REPAIR,
            'payload' => array_merge($this->mechanicalIntakePayload(), [
                'issue_reported_date' => '2026-08-01',
                'issue_details' => 'Second issue',
                'allocated_to' => 'Other Mechanic',
            ]),
        ]);

        $this->assertSame(2, CarStatusHistory::query()
            ->where('car_id', $car->id)
            ->where('new_status', Car::FLEET_STATUS_MECHANICAL_REPAIR)
            ->count());

        $firstEpisode->refresh();
        $this->assertSame('2026-07-20', $firstEpisode->status_data['completed_date']);

        $secondEpisode = CarStatusHistory::query()
            ->where('car_id', $car->id)
            ->where('new_status', Car::FLEET_STATUS_MECHANICAL_REPAIR)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('Other Mechanic', $secondEpisode->status_data['allocated_to']);
        $this->assertSame('Second issue', $secondEpisode->status_data['issue_details']);
    }

    /**
     * @return array<string, mixed>
     */
    private function mechanicalIntakePayload(): array
    {
        return [
            'issue_reported_date' => '2026-07-01',
            'issue_details' => 'Brake failure',
            'allocation_date' => '2026-07-02',
            'allocated_to' => 'Sam Mechanic',
        ];
    }

    private function createCar(string $registration, string $fleetStatus): Car
    {
        return Car::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'car_model_id' => $this->carModelId,
            'registration' => $registration,
            'fleet_status' => $fleetStatus,
            'createdBy' => $this->user->id,
            'updatedBy' => $this->user->id,
        ]);
    }
}
