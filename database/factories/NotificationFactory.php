<?php

namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'       => User::factory(),
            'message'       => $this->faker->text(),
            'status'        => $this->faker->randomElement(NotificationStatus::cases())->value,
            'channel'       => $this->faker->randomElement(NotificationChannel::values()),
            'error_message' => null,
        ];
    }
}
