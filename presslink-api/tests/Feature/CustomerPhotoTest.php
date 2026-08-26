<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_upload_a_profile_photo(): void
    {
        Storage::fake('public');

        $customer = Customer::factory()->create();
        $token = $customer->createToken('test')->plainTextToken;
        $photo = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/customer/photo', ['photo' => $photo]);

        $response->assertOk();
        $this->assertNotNull($customer->fresh()->photo_path);
        Storage::disk('public')->assertExists($customer->fresh()->photo_path);
        $this->assertNotNull($response->json('photo_url'));
    }

    public function test_uploading_a_new_photo_replaces_the_previous_one(): void
    {
        Storage::fake('public');

        $customer = Customer::factory()->create();
        $token = $customer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/customer/photo', ['photo' => UploadedFile::fake()->image('first.jpg')]);
        $firstPath = $customer->fresh()->photo_path;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/customer/photo', ['photo' => UploadedFile::fake()->image('second.jpg')]);

        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($customer->fresh()->photo_path);
    }

    public function test_a_non_image_file_is_rejected(): void
    {
        Storage::fake('public');

        $customer = Customer::factory()->create();
        $token = $customer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/customer/photo', ['photo' => UploadedFile::fake()->create('doc.pdf', 100)])
            ->assertUnprocessable();
    }

    public function test_customer_can_delete_their_photo(): void
    {
        Storage::fake('public');

        $customer = Customer::factory()->create();
        $token = $customer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/customer/photo', ['photo' => UploadedFile::fake()->image('avatar.jpg')]);
        $path = $customer->fresh()->photo_path;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->delete('/api/v1/customer/photo')
            ->assertOk();

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($customer->fresh()->photo_path);
    }
}
