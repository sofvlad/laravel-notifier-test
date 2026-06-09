<?php

namespace Tests\Feature\Api;

use App\Enums\ReportStatus;
use App\Models\NotificationsReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationsReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user  = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    public function test_it_can_request_report_generation(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->withHeader('Idempotency-Key', 'test-' . Str::uuid())
            ->postJson('/api/v1/reports/notifications/generate', [
                'period_start' => '2025-01-01',
                'period_end'   => '2025-01-31',
                'user_id'      => $this->user->id,
            ]);

        $response->assertStatus(202)
            ->assertJsonStructure([
                'report_uuid',
                'status',
                'period',
                'message',
            ]);

        $this->assertDatabaseHas('notifications_reports', [
            'user_id' => $this->user->id,
            'status'  => ReportStatus::COMPLETED->value,
        ]);
    }

    public function test_it_validates_period_dates(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->withHeader('Idempotency-Key', 'test-' . Str::uuid())
            ->postJson('/api/v1/reports/notifications/generate', [
                'period_start' => 'invalid-date',
                'period_end'   => '2025-01-31',
            ]);

        $response->assertStatus(422);
    }

    public function test_it_validates_period_does_not_exceed_90_days(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->withHeader('Idempotency-Key', 'test-' . Str::uuid())
            ->postJson('/api/v1/reports/notifications/generate', [
                'period_start' => '2025-01-01',
                'period_end'   => '2025-06-01',
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Period cannot exceed 90 days']);
    }

    public function test_it_can_get_report_with_user(): void
    {
        $report = NotificationsReport::factory()->processing()->create([
            'user_id'    => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/reports/notifications/' . $report->uuid);

        $response->assertStatus(200)
            ->assertJson([
                'report_uuid' => $report->uuid,
                'status'      => ReportStatus::PROCESSING->value,
            ]);
    }

    public function test_it_can_get_report_without_user(): void
    {
        $report = NotificationsReport::factory()->processing()->create([
            'created_by' => $this->user->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/reports/notifications/' . $report->uuid);

        $response->assertStatus(200)
            ->assertJson([
                'report_uuid' => $report->uuid,
                'status'      => ReportStatus::PROCESSING->value,
            ]);
    }

    public function test_it_can_get_report_by_another_user(): void
    {
        $report = NotificationsReport::factory()->processing()->create([
            'created_by' => User::factory()->create()->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/reports/notifications/' . $report->uuid);

        $response->assertStatus(404);
    }

    public function test_it_returns_404_for_nonexistent_report(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/reports/notifications/99999');

        $response->assertStatus(404);
    }

    public function test_it_cannot_download_report_from_other_user(): void
    {
        $report = NotificationsReport::factory()->completed()->create([
            'user_id'    => User::factory()->create()->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/reports/notifications/' . $report->uuid . '/download');

        $response->assertStatus(404);
    }

    public function test_it_cannot_download_pending_report(): void
    {
        $report = NotificationsReport::factory()->pending()->create([
            'user_id'    => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/reports/notifications/' . $report->uuid . '/download');

        $response->assertStatus(400)
            ->assertJsonFragment(['status' => 'pending']);
    }
}
