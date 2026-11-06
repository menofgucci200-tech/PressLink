<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Au comptoir, chaque moyen de paiement se reconnaît à son logo.
 */
class PaymentMethodLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_operator_logo_exists(): void
    {
        foreach (PaymentMethod::cases() as $method) {
            if ($method->logo() !== null) {
                $this->assertFileExists(public_path($method->logo()), $method->label());
            }
        }

        $this->assertNull(PaymentMethod::Cash->logo());
        $this->assertNull(PaymentMethod::Card->logo());
    }

    public function test_the_logo_component_shows_the_operator_logo_or_a_drawn_icon(): void
    {
        $this->blade('<x-payment-method-logo :method="$m" />', ['m' => PaymentMethod::Wave])
            ->assertSee(asset('images/paiement/wave.webp'), false);

        $this->blade('<x-payment-method-logo method="especes" />')
            ->assertSee('<svg', false)
            ->assertDontSee('<img', false);
    }
}
