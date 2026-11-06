<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\ActivityAction;
use App\Enums\OrderStatus;
use App\Enums\PressingRole;
use App\Livewire\Orders\Show as OrdersShow;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPhoto;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Photos des articles d'une commande : prises par le pressing, visibles
 * par le client dans l'application, supprimées 30 jours après le retrait.
 */
class OrderPhotosTest extends TestCase
{
    use RefreshDatabase;

    private Pressing $pressing;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(OrderPhoto::DISK);
        $this->pressing = Pressing::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->pressing->customers()->attach($this->customer, ['joined_at' => now()]);
    }

    private function staff(PressingRole $role = PressingRole::Admin): User
    {
        $user = User::factory()->create();
        $this->pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);

        return $user;
    }

    private function order(): Order
    {
        return (new CreateOrderAction)->handle($this->pressing, $this->customer, [
            ['service_id' => null, 'service_variant_id' => null, 'name' => 'Chemise', 'color' => null, 'unit_price_fcfa' => 1000, 'quantity' => 2],
        ]);
    }

    private function uploadPhoto(Order $order, User $user): void
    {
        Livewire::actingAs($user)->test(OrdersShow::class, ['order' => $order])
            ->set('photo', UploadedFile::fake()->image('chemise.jpg', 2400, 1800))
            ->call('savePhoto')
            ->assertHasNoErrors();
    }

    public function test_staff_photograph_the_items_and_the_photo_is_reduced_and_private(): void
    {
        $order = $this->order();
        $this->uploadPhoto($order, $this->staff(PressingRole::Employee));

        $photo = $order->photos()->sole();
        Storage::disk(OrderPhoto::DISK)->assertExists([$photo->path, $photo->thumb_path]);
        $this->assertStringStartsWith("order-photos/{$this->pressing->id}/{$order->id}/", $photo->path);

        [$width, $height] = getimagesizefromstring(Storage::disk(OrderPhoto::DISK)->get($photo->path));
        $this->assertSame([OrderPhoto::MAX_SIDE, 1200], [$width, $height]);
        $this->assertSame(OrderPhoto::THUMB_SIDE, getimagesizefromstring(Storage::disk(OrderPhoto::DISK)->get($photo->thumb_path))[0]);

        // Lien signé uniquement.
        $this->get('/photos-commandes/'.$photo->id)->assertForbidden();
        $this->get($photo->url())->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->get($photo->url(thumb: true))->assertOk();
    }

    public function test_the_order_page_shows_the_photos_and_the_camera_buttons(): void
    {
        $order = $this->order();
        OrderPhoto::factory()->for($order)->create();

        Livewire::actingAs($this->staff())->test(OrdersShow::class, ['order' => $order])
            ->assertSee('Photos des articles')
            ->assertSee('data-testid="photo-camera"', false)
            ->assertSee('Galerie')
            ->assertSee('/photos-commandes/', false);
    }

    public function test_photos_are_limited_and_closed_on_cancelled_orders(): void
    {
        $order = $this->order();
        $admin = $this->staff();
        OrderPhoto::factory()->count(OrderPhoto::MAX_PER_ORDER)->for($order)->create();

        Livewire::actingAs($admin)->test(OrdersShow::class, ['order' => $order])
            ->set('photo', UploadedFile::fake()->image('a.jpg', 800, 600))
            ->call('savePhoto')
            ->assertHasErrors('photo');

        $cancelled = $this->order();
        Order::whereKey($cancelled->id)->update(['status' => OrderStatus::Annulee->value]);
        $cancelled->refresh();

        Livewire::actingAs($admin)->test(OrdersShow::class, ['order' => $cancelled])
            ->assertDontSee('data-testid="photo-camera"', false)
            ->set('photo', UploadedFile::fake()->image('a.jpg', 800, 600))
            ->call('savePhoto')
            ->assertHasErrors('photo');

        $this->assertSame(0, $cancelled->photos()->count());
    }

    public function test_only_the_author_or_an_admin_removes_a_photo_and_it_is_logged(): void
    {
        $order = $this->order();
        $author = $this->staff(PressingRole::Employee);
        $colleague = $this->staff(PressingRole::Employee);
        $this->uploadPhoto($order, $author);
        $photo = $order->photos()->sole();

        Livewire::actingAs($colleague)->test(OrdersShow::class, ['order' => $order])
            ->call('removePhoto', $photo->id);
        $this->assertModelExists($photo);

        Livewire::actingAs($this->staff())->test(OrdersShow::class, ['order' => $order])
            ->call('removePhoto', $photo->id);

        $this->assertModelMissing($photo);
        Storage::disk(OrderPhoto::DISK)->assertMissing([$photo->path, $photo->thumb_path]);
        $this->assertDatabaseHas('activity_logs', ['order_id' => $order->id, 'action' => ActivityAction::OrderPhotoRemoved->value]);
    }

    public function test_the_customer_sees_the_photos_of_their_order_in_the_app(): void
    {
        $order = $this->order();
        $photo = OrderPhoto::factory()->for($order)->create();
        Storage::disk(OrderPhoto::DISK)->put($photo->path, 'x');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->customer->createToken('t')->plainTextToken)
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonCount(1, 'photos')
            ->assertJsonMissingPath('photos.0.path');

        $this->get($response->json('photos.0.url'))->assertOk();
        $this->assertNotNull($response->json('photos.0.thumb_url'));

        // Un autre client ne voit pas la commande.
        $this->app['auth']->forgetGuards();
        $other = Customer::factory()->create();
        $this->withHeader('Authorization', 'Bearer '.$other->createToken('t')->plainTextToken)
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertForbidden();
    }

    public function test_photos_are_purged_thirty_days_after_pickup(): void
    {
        $old = $this->order();
        Order::whereKey($old->id)->update(['status' => OrderStatus::Recuperee->value, 'recovered_at' => now()->subDays(OrderPhoto::RETENTION_DAYS + 1)]);
        $oldPhoto = OrderPhoto::factory()->for($old)->create();
        Storage::disk(OrderPhoto::DISK)->put($oldPhoto->path, 'x');

        $recent = $this->order();
        Order::whereKey($recent->id)->update(['status' => OrderStatus::Recuperee->value, 'recovered_at' => now()->subDays(3)]);
        $recentPhoto = OrderPhoto::factory()->for($recent)->create();

        $pending = OrderPhoto::factory()->for($this->order())->create();

        $this->artisan('presslink:purge-order-photos')->assertSuccessful();

        $this->assertModelMissing($oldPhoto);
        Storage::disk(OrderPhoto::DISK)->assertMissing($oldPhoto->path);
        $this->assertModelExists($recentPhoto);
        $this->assertModelExists($pending);
    }

    public function test_signed_links_expire(): void
    {
        $photo = OrderPhoto::factory()->for($this->order())->create();
        Storage::disk(OrderPhoto::DISK)->put($photo->path, 'x');
        $url = URL::temporarySignedRoute('order-photos.show', now()->addMinute(), ['photo' => $photo->id]);

        $this->travel(2)->minutes();
        $this->get($url)->assertForbidden();
    }
}
