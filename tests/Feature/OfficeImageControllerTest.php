<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use Tests\TestCase;

class OfficeImageControllerTest extends TestCase
{
    
    use LazilyRefreshDatabase;

    public function test_itUploadsAnImageAndStoreItUnderTheOffice()
    {
        Storage::fake();

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->post("/api/offices/{$office->id}/images", [
            'image' => UploadedFile::fake()->image('image.jpg')
        ]);

        $response->assertCreated();
        Storage::assertExists($response->json('data.path'));
    }

    public function test_itDeletesAnImage()
    {
        Storage::put('/office_image.jpg', 'empty');   

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->images()->create([
            'path' => 'image.jpg'
        ]);

        $image = $office->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertOk();
        Storage::assertMissing('office_image.jpg');
    }

    public function test_itDoesntDeleteTheOnlyImage()
    {

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $image = $office->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['image' => 'cannot delete the only image.']);
    }

    public function test_itDoesntDeleteTheFeaturedImage()
    {

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->images()->create([
            'path' => 'image.jpg'
        ]);

        $image = $office->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $office->update(['featured_image_id' => $image->id]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['image' => 'cannot delete the featured image.']);
    }

    public function test_itDoesntDeleteImageThatBelongsToAntherResource()
    {

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $office2 = Office::factory()->for($user)->create();

        $image = $office2->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertNotFound();
        // $response->assertUnprocessable();
        // $response->assertJsonValidationErrors(['image' => 'cannot delete this image.']);
    }
}
