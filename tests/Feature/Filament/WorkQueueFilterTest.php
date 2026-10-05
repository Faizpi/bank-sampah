<?php

namespace Tests\Feature\Filament;

use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupRequest;
use App\Support\WorkQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WorkQueueFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_pickup_queue_uses_the_confirmed_schedule_instead_of_the_requested_date(): void
    {
        $dusun = Dusun::create(['code' => 'QUEUE-DS', 'name' => 'Dusun uji']);
        Rw::create(['dusun_id' => $dusun->id, 'code' => 'QUEUE-RW', 'name' => 'RW uji']);
        $today = PickupRequest::factory()->create(['status' => PickupStatus::Scheduled, 'selected_date' => today()->subDay(), 'scheduled_date' => today()]);
        $tomorrow = PickupRequest::factory()->create(['status' => PickupStatus::Scheduled, 'selected_date' => today(), 'scheduled_date' => today()->addDay()]);
        $overdue = PickupRequest::factory()->create(['status' => PickupStatus::Scheduled, 'selected_date' => today()->subDays(2), 'scheduled_date' => today()->subDay()]);
        self::assertSame([$today->id], WorkQueue::apply(PickupRequest::query(), 'pickup_today')->pluck('id')->all());
        self::assertSame([$overdue->id], WorkQueue::apply(PickupRequest::query(), 'pickup_overdue')->pluck('id')->all());
        self::assertNotContains($tomorrow->id, WorkQueue::apply(PickupRequest::query(), 'pickup_today')->pluck('id')->all());
    }
}
