<?php

namespace Tests\Feature;

use App\Http\Resources\OfficeResource;
use App\Models\Image;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\OfficePendingApproval;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tests\TestCase;
use Illuminate\Support\Facades\Storage;

class OfficeControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_itListAllOfficesInPaginatedWay()
    {
        Office::factory(3)->create();

        $response = $this->get('/api/offices');

        // dd($response->json());

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $this->assertNotNull($response->json('data')[0]['id']);
        $this->assertNotNull($response->json('meta'));
        $this->assertNotNull($response->json('links'));
    }

    public function test_itOnlyListOfficesThatAreNotHiddenAndApproved()
    {
        Office::factory(3)->create();
        Office::factory()->create(['hidden' => true]);
        Office::factory()->create(['approval_status' => Office::APPROVAL_PENDING]);

        $response = $this->get('/api/offices');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_itOnlyListOfficesIncludingHiddenAndApprovedIfFilteringForCurrentLoggedInUser()
    {
        $user = User::factory()->create();

        Office::factory(3)->for($user)->create();
        Office::factory()->for($user)->create(['hidden' => true]);
        Office::factory()->for($user)->create(['approval_status' => Office::APPROVAL_PENDING]);

        $this->actingAs($user);

        $response = $this->get('/api/offices?user_id='. $user->id);

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
    }

    public function test_itFiltersByUserId()
    {
        Office::factory(3)->create();
        $host = User::factory()->create();
        $office = Office::factory()->for($host)->create();

        $response = $this->get(
            '/api/offices?user_id=' . $host->id
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($office->id, $response->json('data')[0]['id']);
    }

    public function test_itFiltersByVisitorId()
    {
        Office::factory(3)->create();
        $user = User::factory()->create();
        $office = Office::factory()->create();

        Reservation::factory()->for(Office::factory())->create();
        Reservation::factory()->for($office)->for($user)->create();

        $response = $this->get(
            '/api/offices?visitor_id=' . $user->id
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($office->id, $response->json('data')[0]['id']);
    }

    public function test_itFiltersByTags()
    {
        $tags = Tag::factory(2)->create();
        $office = Office::factory()->hasAttached($tags)->create();
        Office::factory()->hasAttached($tags->first())->create();
        Office::factory()->create();

        $response = $this->get(
            '/api/offices?' . http_build_query([
                'tags' => $tags->pluck('id')->toArray()
            ])
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $office->id);
    }

    public function test_itIncludesImagesTagsAndUser()
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        // $image = Image::factory()->create();
        
        $office = Office::factory()->for($user)->create();
        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.jpg']);

        $response = $this->get('/api/offices');

        $response->assertOk();

        $this->assertIsArray($response->json('data')[0]['tags']);
        $this->assertCount(1, $response->json('data')[0]['tags']);
        $this->assertIsArray($response->json('data')[0]['images']);
        $this->assertCount(1, $response->json('data')[0]['images']);
        $this->assertEquals($user->id, $response->json('data')[0]['user']['id']);
    }

    public function test_itReturnsTheNumberOfActiveReservations()
    {
        $office = Office::factory()->create();
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_ACTIVE]);
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_CANCELED]);

        $response = $this->get('/api/offices');

        $response->assertOk();
        $this->assertEquals(1, $response->json('data')[0]['reservations_count']);

    }

    public function test_itOrderByDistanceWhereCoordinatesAreProvided()
    {
        // 38.720661384644046
        // -9.16044783453807

        $office = Office::factory()->create([
            'lat' => '39.088216161034566',
            'lng' => '-9.252385883312964',
            'title' => 'Torres Vedras'
        ]);

        $office2 = Office::factory()->create([
            'lat' => '39.739647233937944',
            'lng' => '-8.80469301783934',
            'title' => 'Leiria'
        ]);

        $response = $this->get('api/offices?lat=38.720661384644046&lng=-9.16044783453807');
        $response->assertOk();
        $this->assertEquals('Torres Vedras', $response->json('data')[0]['title']);
        $this->assertEquals('Leiria', $response->json('data')[1]['title']);

        $response = $this->get('api/offices');
        $response->assertOk();
        $this->assertEquals('Leiria', $response->json('data')[1]['title']);
        $this->assertEquals('Torres Vedras', $response->json('data')[0]['title']);
    }
    
    public function test_itShowsTheOffice()
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        // $image = Image::factory()->create();
        
        $office = Office::factory()->for($user)->create();
        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.jpg']);

        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_ACTIVE]);
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_CANCELED]);

        $response = $this->get('api/offices/' . $office->id);

        $this->assertEquals(1, $response->json('data')['reservations_count']);
        $this->assertIsArray($response->json('data')['tags']);
        $this->assertCount(1, $response->json('data')['tags']);
        $this->assertIsArray($response->json('data')['images']);
        $this->assertCount(1, $response->json('data')['images']);
        $this->assertEquals($user->id, $response->json('data')['user']['id']);
    }

    public function test_itCreatesAnOffice()
    {
        Notification::fake();
        
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $user = User::factory()->create();
        $tags = Tag::factory(2)->create();

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/offices', Office::factory()->raw([
            'tags' => $tags->pluck('id')->toArray()
        ]));

        $response->assertCreated()
            // ->assertJsonPath('data.title', 'Office in Arkansas')
            ->assertJsonPath('data.reservations_count', 0)
            ->assertJsonPath('data.approval_status', Office::APPROVAL_PENDING)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonCount(2, 'data.tags');

        $this->assertDatabaseHas('offices', [
            'id' => $response->json('data.id')
        ]);

        Notification::assertSentTo($admin, OfficePendingApproval::class);
    }

    public function test_itDoesntAllowCreatingIfScopeIsNotProvided()
    {
        $user = User::factory()->createQuietly();
        
        $token = $user->createToken('test', []);

        $response = $this->postJson('/api/offices', [], [
            'Authorization' => 'Bearer ' . $token->plainTextToken
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_itAllowsCreatingIfScopeIsProvided()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['office.create']);

        $response = $this->postJson('/api/offices');

        $this->assertNotEquals(Response::HTTP_FORBIDDEN, $response->status());
    }

    public function test_itUpdatesAnOffice()
    {
        $user = User::factory()->create();
        $tags = Tag::factory(3)->create();
        $anotherTag = Tag::factory()->create();
        $office = Office::factory()->for($user)->create();
        
        $office->tags()->attach($tags);

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/'.$office->id, [
            'title' => 'Amazing office',
            'tags' => [$tags[0]->id, $anotherTag->id],
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.tags')
            ->assertJsonPath('data.tags.0.id', $tags[0]->id)
            ->assertJsonPath('data.tags.1.id', $anotherTag->id)
            ->assertJsonPath('data.title', 'Amazing office');

    }

    public function test_itUpdatesTheFeaturedImageOfAnOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        
        $image = $office->images()->create([
            'path' => 'image.jpg',
        ]);

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/'.$office->id, [
            'featured_image_id' => $image->id,
        ]);
        
        $response->assertOk()
            ->assertJsonPath('data.featured_image_id', $image->id);
        
    }

    public function test_itDoesntUpdateFeaturedImageThatBelongsToAnotherOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $office2 = Office::factory()->create();
        
        $image = $office2->images()->create([
            'path' => 'image.jpg',
        ]);

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/'.$office->id, [
            'featured_image_id' => $image->id,
        ]);
        
        $response->assertUnprocessable()->assertInvalid('featured_image_id');
        
    }

    public function test_itDoesntUpdateOfficeThatDoesntBelongToUser()
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();
        $office = Office::factory()->for($anotherUser)->create();

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/offices/'.$office->id, [
            'title' => 'Amazing office',
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);


    }

    public function test_itMarksTheOfficeAsPendingIfDirtyAndSendNotificationToAdmin()
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        Notification::fake();

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/'.$office->id, [
            'lat' => '38.720661384644047',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('offices', [
            'id' => $office->id,
            'approval_status' => Office::APPROVAL_PENDING,
        ]);

        Notification::assertSentTo($admin, OfficePendingApproval::class);
    }

    public function test_itCanDeleteOffices()
    {
        Storage::put('/office_image.jpg', 'empty');

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $image = $office->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson('/api/offices/'.$office->id);

        $response->assertOk();

        $this->assertSoftDeleted($office);

        // $response->assertOk();
        Storage::assertMissing('office_image.jpg');

    }
    
    public function test_itCannotDeleteOfficesThatHasReservations()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        Reservation::factory(3)->for($office)->create();

        $this->actingAs($user);

        $response = $this->deleteJson('/api/offices/'.$office->id);

        $response->assertUnprocessable();

        $this->assertNotSoftDeleted($office);
    }

    /**
     * A basic feature test example.
     *
     * @return void
     */
    // public function test_example()
    // {
        
    // }
}
