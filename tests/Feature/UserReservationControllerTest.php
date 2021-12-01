<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Reservation;
use App\Models\User;
use Database\Factories\ReservationFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserReservationControllerTest extends TestCase
{

    use LazilyRefreshDatabase;

    /**
     * A basic feature test example.
     *
     * @return void
     */
    // public function test_example()
    // {
    //     $response = $this->get('/');

    //     $response->assertStatus(200);
    // }

    public function test_itListsReservationsThatBelongToTheUser()
    {
        $user = User::factory()->create();

        $reservation = Reservation::factory()->for($user)->create();

        $image = $reservation->office->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $reservation->office()->update(['featured_image_id' => $image->id]);

        Reservation::factory()->for($user)->count(2)->create();
        Reservation::factory()->count(3)->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/reservations');

        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure(['data', 'meta', 'links']);
        $response->assertJsonStructure(['data' => ['*' => ['id', 'office']]]);
        $response->assertJsonPath('data.0.office.featured_image.id', $image->id);
    }

    public function test_itListsReservationsFilteredByDateRange()
    {
        $user = User::factory()->create();

        $fromDate = '2021-03-03';
        $toDate = '2021-04-04';

        // Within the date range
        $reservations =  Reservation::factory()->for($user)->createMany([
            [
                'start_date' => '2021-03-03',
                'end_date' => '2021-03-15'
            ],
            [
                'start_date' => '2021-03-25',
                'end_date' => '2021-04-15'
            ],
            [
                'start_date' => '2021-03-25',
                'end_date' => '2021-03-29'
            ],
            [
                'start_date' => '2021-03-01',
                'end_date' => '2021-04-15'
            ],
        ]);

        // Within the date range but belongs to another user
        Reservation::factory()->create([
            'start_date' => '2021-03-03',
            'end_date' => '2021-03-15'
        ]);

        // Outside the date range
        Reservation::factory()->for($user)->create([
            'start_date' => '2021-02-25',
            'end_date' => '2021-03-01'
        ]);

        Reservation::factory()->for($user)->create([
            'start_date' => '2021-05-25',
            'end_date' => '2021-06-01'
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/reservations?'.http_build_query([
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]));

        $response->assertJsonCount(4, 'data');

        $this->assertEquals($reservations->pluck('id')->toArray(), collect($response->json('data'))->pluck('id')->toArray());
    }

    public function test_itFiltersResultsByStatus()
    {
        $user = User::factory()->create();

        $reservation = Reservation::factory()->for($user)->create([
            'status' => Reservation::STATUS_ACTIVE
        ]);

        $reservation2 = Reservation::factory()->for($user)->cancelled()->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/reservations?'.http_build_query([
            'status' => Reservation::STATUS_ACTIVE,
        ]));

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $reservation->id);
    }

    public function test_itFiltersResultsByOffice()
    {
        $user = User::factory()->create();

        $office = Office::factory()->create();

        $reservation = Reservation::factory()->for($office)->for($user)->create();

        $reservation2 = Reservation::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/reservations?'.http_build_query([
            'office_id' => $office->id,
        ]));

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $reservation->id);
    }

    public function test_itMakesReservations()
    {
        $user = User::factory()->create();

        $office = Office::factory()->create([
            'price_per_day' => 1_000,
            'monthly_discount' => 10,
        ]);

        $this->actingAs($user);

        $response = $this->postJson('/api/reservations', [
            'office_id' => $office->id,
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(40),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.price', 36000);
        $response->assertJsonPath('data.status', Reservation::STATUS_ACTIVE);
        $response->assertJsonPath('data.user_id', $user->id);
        $response->assertJsonPath('data.office_id', $office->id);
    }

    public function test_itCannotMakeReservationOnNonExistingOffice()
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson('/api/reservations', [
            'office_id' => 10000,
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(41),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['office_id' => 'Invalid office_id']);
    }

    public function test_itCannotMakeReservationOnOfficeThatBelongsToTheUser()
    {
        $user = User::factory()->create();

        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->postJson('/api/reservations', [
            'office_id' => $office->id,
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(41),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['office_id' => 'You cannot make a reservation on your own office']);
    }

    public function test_itCannotMakeReservationLessThan2Days()
    {
        $user = User::factory()->create();

        $office = Office::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson('/api/reservations', [
            'office_id' => $office->id,
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(1),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['start_date' => 'You cannot make a reservation for only 1 day']);
    }

    public function test_itMakeReservationFor2Days()
    {
        $user = User::factory()->create();

        $office = Office::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson('/api/reservations', [
            'office_id' => $office->id,
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(2),
        ]);

        $response->assertCreated();
    }

    public function test_itCannotMakeReservationThatsConflicting()
    {
        $user = User::factory()->create();

        $fromDate = now()->addDay(1)->toDateString();
        $toDate = now()->addDay(15)->toDateString();

        $office = Office::factory()->create();

        Reservation::factory()->for($office)->create([
            'start_date' => now()->addDay(2),
            'end_date' => $toDate
        ]);

        $this->actingAs($user);

        $response = $this->postJson('/api/reservations', [
            'office_id' => $office->id,
            'start_date' => $fromDate,
            'end_date' => $toDate,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['office_id' => 'You cannot make a reservation during this time']);
    }
}
