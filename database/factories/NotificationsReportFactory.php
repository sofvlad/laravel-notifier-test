<?php

namespace Database\Factories;

use App\Enums\ReportStatus;
use App\Models\NotificationsReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationsReport>
 */
class NotificationsReportFactory extends Factory
{
    protected $model = NotificationsReport::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid'          => $this->faker->uuid(),
            'period_start'  => $this->faker->dateTimeBetween('-30 days', '-1 day'),
            'period_end'    => $this->faker->dateTimeBetween('now', '+30 days'),
            'status'        => $this->faker->randomElement(ReportStatus::cases())->value,
            'file_path'     => null,
            'error_message' => null,
            'started_at'    => null,
            'completed_at'  => null,
        ];
    }

    /**
     * Indicate that the report is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReportStatus::PENDING->value,
        ]);
    }

    /**
     * Indicate that the report is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'     => ReportStatus::PROCESSING->value,
            'started_at' => now(),
        ]);
    }

    /**
     * Indicate that the report is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => ReportStatus::COMPLETED->value,
            'started_at'   => now()->subMinutes(5),
            'completed_at' => now(),
            'file_path'    => 'reports/' . $this->faker->uuid() . '.json',
        ]);
    }

    /**
     * Indicate that the report has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'        => ReportStatus::FAILED->value,
            'started_at'    => now()->subMinutes(5),
            'completed_at'  => now(),
            'error_message' => $this->faker->sentence(),
        ]);
    }
}
