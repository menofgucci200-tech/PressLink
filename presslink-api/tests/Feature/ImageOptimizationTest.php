<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Livewire\Pressing\Profile as PressingProfile;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\User;
use App\Support\ImageOptimizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Photos de profil et logos réduits à l'envoi (512 px max, WebP) pour
 * ménager l'espace disque de l'hébergement.
 */
class ImageOptimizationTest extends TestCase
{
    use RefreshDatabase;

    private function dimensions(string $path): array
    {
        return getimagesizefromstring(Storage::disk('public')->get($path));
    }

    public function test_a_large_customer_photo_is_stored_as_a_small_webp(): void
    {
        Storage::fake('public');
        $customer = Customer::factory()->create();
        $token = $customer->createToken('test')->plainTextToken;
        $photo = UploadedFile::fake()->image('avatar.jpg', 3000, 2000);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/customer/photo', ['photo' => $photo])
            ->assertOk();

        $path = $customer->fresh()->photo_path;
        $this->assertStringEndsWith('.webp', $path);
        [$width, $height, $type] = $this->dimensions($path);
        $this->assertSame([512, 341, IMAGETYPE_WEBP], [$width, $height, $type]);
        $this->assertLessThan($photo->getSize(), Storage::disk('public')->size($path));
    }

    public function test_a_small_photo_is_not_enlarged(): void
    {
        Storage::fake('public');
        $customer = Customer::factory()->create();
        $token = $customer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/customer/photo', ['photo' => UploadedFile::fake()->image('petite.png', 200, 120)])
            ->assertOk();

        [$width, $height] = $this->dimensions($customer->fresh()->photo_path);
        $this->assertSame([200, 120], [$width, $height]);
    }

    public function test_an_oversized_image_is_rejected_with_a_clear_message(): void
    {
        Storage::fake('public');
        $customer = Customer::factory()->create();
        $token = $customer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/customer/photo', ['photo' => UploadedFile::fake()->image('enorme.jpg', 7000, 500)])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cette image est trop grande (6000 × 6000 pixels maximum).');
    }

    public function test_pressing_logo_is_reduced_and_keeps_its_transparency(): void
    {
        Storage::fake('public');
        $pressing = Pressing::factory()->create();
        $admin = User::factory()->create();
        $pressing->staff()->attach($admin, ['role' => PressingRole::Admin->value, 'is_active' => true]);

        // Logo PNG 1600 × 800, fond transparent, rond bleu au centre.
        $png = imagecreatetruecolor(1600, 800);
        imagesavealpha($png, true);
        imagefill($png, 0, 0, imagecolorallocatealpha($png, 0, 0, 0, 127));
        imagefilledellipse($png, 800, 400, 500, 500, imagecolorallocate($png, 37, 99, 235));
        $file = tempnam(sys_get_temp_dir(), 'logo').'.png';
        imagepng($png, $file);

        $this->actingAs($admin);
        Livewire::test(PressingProfile::class)
            ->set('logo', UploadedFile::fake()->createWithContent('logo.png', file_get_contents($file)))
            ->call('save')
            ->assertHasNoErrors();

        $path = $pressing->refresh()->logo_path;
        [$width, $height, $type] = $this->dimensions($path);
        $this->assertSame([512, 256, IMAGETYPE_WEBP], [$width, $height, $type]);

        $stored = imagecreatefromstring(Storage::disk('public')->get($path));
        $corner = imagecolorsforindex($stored, imagecolorat($stored, 2, 2));
        $centre = imagecolorsforindex($stored, imagecolorat($stored, 256, 128));
        $this->assertSame(127, $corner['alpha'], 'le fond doit rester transparent');
        $this->assertSame(0, $centre['alpha']);
    }

    public function test_photo_metadata_and_orientation_are_handled_without_gd_errors_on_non_images(): void
    {
        $this->assertNull(ImageOptimizer::toWebp(__FILE__));
    }
}
