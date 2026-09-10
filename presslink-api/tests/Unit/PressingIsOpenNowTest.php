<?php

namespace Tests\Unit;

use App\Models\Pressing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PressingIsOpenNowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_open_when_within_todays_hours(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 10:00')); // jeudi

        $pressing = Pressing::factory()->create([
            'opening_hours' => ['jeudi' => ['closed' => false, 'open' => '08:00', 'close' => '18:00']],
        ]);

        $this->assertTrue($pressing->isOpenNow());
    }

    public function test_closed_before_opening_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 06:00'));

        $pressing = Pressing::factory()->create([
            'opening_hours' => ['jeudi' => ['closed' => false, 'open' => '08:00', 'close' => '18:00']],
        ]);

        $this->assertFalse($pressing->isOpenNow());
    }

    public function test_closed_after_closing_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 19:00'));

        $pressing = Pressing::factory()->create([
            'opening_hours' => ['jeudi' => ['closed' => false, 'open' => '08:00', 'close' => '18:00']],
        ]);

        $this->assertFalse($pressing->isOpenNow());
    }

    public function test_closed_when_day_marked_closed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 10:00'));

        $pressing = Pressing::factory()->create([
            'opening_hours' => ['jeudi' => ['closed' => true, 'open' => '08:00', 'close' => '18:00']],
        ]);

        $this->assertFalse($pressing->isOpenNow());
    }

    public function test_open_when_no_hours_configured(): void
    {
        $pressing = Pressing::factory()->create(['opening_hours' => null]);

        $this->assertTrue($pressing->isOpenNow());
    }
}
