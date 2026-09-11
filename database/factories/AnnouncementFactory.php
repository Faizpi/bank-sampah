<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Communication\Enums\AnnouncementAudience;
use App\Domain\Communication\Enums\AnnouncementStatus;
use App\Domain\Communication\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Announcement> */
final class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    public function definition(): array
    {
        return ['announcement_number' => 'ANN-'.$this->faker->unique()->numerify('##########'), 'title' => 'Informasi layanan', 'body' => '<p>Informasi layanan bank sampah.</p>', 'audience' => AnnouncementAudience::Public, 'publish_start' => now()->subHour(), 'publish_end' => null, 'status' => AnnouncementStatus::Published, 'priority' => 0, 'created_by' => User::factory(), 'published_by' => User::factory(), 'published_at' => now()];
    }
}
