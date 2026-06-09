<?php

namespace Tests\Unit\Services\Notifications\Reports;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Enums\ReportStatus;
use App\Models\Notification;
use App\Models\NotificationsReport;
use App\Models\User;
use App\Services\Notifications\Reports\NotificationsReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationsReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected NotificationsReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user    = User::factory()->create();
        $this->service = new NotificationsReportService;
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Storage::disk('local')->deleteDirectory('reports');
    }

    public function test_it_generates_report_with_correct_statistics(): void
    {
        Notification::create([
            'user_id'  => $this->user->id,
            'message'  => 'Test message 1',
            'channel'  => NotificationChannel::EMAIL->value,
            'status'   => NotificationStatus::SENT->value,
            'priority' => NotificationPriority::DEFAULT->value,
        ]);

        Notification::create([
            'user_id'       => $this->user->id,
            'message'       => 'Test message 2',
            'channel'       => NotificationChannel::EMAIL->value,
            'status'        => NotificationStatus::FAILED->value,
            'error_message' => 'SMTP error',
            'priority'      => NotificationPriority::DEFAULT->value,
        ]);

        Notification::create([
            'user_id'  => $this->user->id,
            'message'  => 'Test message 3',
            'channel'  => NotificationChannel::TELEGRAM->value,
            'status'   => NotificationStatus::SENT->value,
            'priority' => NotificationPriority::DEFAULT->value,
        ]);

        $report = NotificationsReport::create([
            'user_id'      => $this->user->id,
            'period_start' => now()->subDays(10),
            'period_end'   => now(),
            'status'       => ReportStatus::PENDING->value,
        ]);

        $filePath = $this->service->generate($report);

        $report->refresh();

        $this->assertEquals(ReportStatus::COMPLETED->value, $report->status);
        $this->assertNotNull($report->completed_at);
        $this->assertNotNull($filePath);

        $fileSystem = Storage::disk('local');
        $fileSystem->assertExists($filePath);

        $content = json_decode($fileSystem->get($filePath), true);

        $this->assertEquals($report->id, $content['report_id']);
        $this->assertEquals(3, $content['summary']['total_notifications']);
        $this->assertCount(2, $content['by_channel']);
    }

    public function test_it_counts_errors_correctly(): void
    {
        Notification::create([
            'user_id'  => $this->user->id,
            'message'  => 'Sent 1',
            'channel'  => NotificationChannel::EMAIL->value,
            'status'   => NotificationStatus::SENT->value,
            'priority' => NotificationPriority::DEFAULT->value,
        ]);

        Notification::create([
            'user_id'  => $this->user->id,
            'message'  => 'Failed 1',
            'channel'  => NotificationChannel::EMAIL->value,
            'status'   => NotificationStatus::FAILED->value,
            'priority' => NotificationPriority::DEFAULT->value,
        ]);

        Notification::create([
            'user_id'  => $this->user->id,
            'message'  => 'Failed 2',
            'channel'  => NotificationChannel::EMAIL->value,
            'status'   => NotificationStatus::FAILED->value,
            'priority' => NotificationPriority::DEFAULT->value,
        ]);

        Notification::create([
            'user_id'  => $this->user->id,
            'message'  => 'Sent 2',
            'channel'  => NotificationChannel::TELEGRAM->value,
            'status'   => NotificationStatus::SENT->value,
            'priority' => NotificationPriority::DEFAULT->value,
        ]);

        $report = NotificationsReport::create([
            'user_id'      => $this->user->id,
            'period_start' => now()->subDays(10),
            'period_end'   => now(),
            'status'       => ReportStatus::PENDING->value,
        ]);

        $this->service->generate($report);
        $report->refresh();

        $content = json_decode(Storage::disk('local')->get($report->file_path), true);

        $emailChannel    = collect($content['by_channel'])->where('channel', 'email')->first();
        $telegramChannel = collect($content['by_channel'])->where('channel', 'telegram')->first();

        $this->assertEquals(3, $emailChannel['total']);
        $this->assertEquals(2, $emailChannel['errors']);
        $this->assertEquals(1, $telegramChannel['total']);
        $this->assertEquals(0, $telegramChannel['errors']);
    }

    public function test_it_only_includes_notifications_from_period(): void
    {
        Notification::forceCreate([
            'user_id'    => $this->user->id,
            'message'    => 'Old notification',
            'channel'    => NotificationChannel::EMAIL->value,
            'status'     => NotificationStatus::SENT->value,
            'created_at' => now()->subDays(20),
            'priority'   => NotificationPriority::DEFAULT->value,
        ]);

        Notification::forceCreate([
            'user_id'    => $this->user->id,
            'message'    => 'New notification',
            'channel'    => NotificationChannel::EMAIL->value,
            'status'     => NotificationStatus::SENT->value,
            'created_at' => now()->subDays(5),
            'priority'   => NotificationPriority::DEFAULT->value,
        ]);

        $report = NotificationsReport::create([
            'user_id'      => $this->user->id,
            'period_start' => now()->subDays(10),
            'period_end'   => now(),
            'status'       => ReportStatus::PENDING->value,
        ]);

        $this->service->generate($report);
        $report->refresh();

        $content = json_decode(Storage::disk('local')->get($report->file_path), true);
        $this->assertEquals(1, $content['summary']['total_notifications']);
    }

    public function test_it_generates_correct_file_structure(): void
    {
        Notification::create([
            'user_id'  => $this->user->id,
            'message'  => 'Test',
            'channel'  => NotificationChannel::EMAIL->value,
            'status'   => NotificationStatus::SENT->value,
            'priority' => NotificationPriority::DEFAULT->value,
        ]);

        $report = NotificationsReport::create([
            'user_id'      => $this->user->id,
            'period_start' => now()->subDays(10),
            'period_end'   => now(),
            'status'       => ReportStatus::PENDING->value,
        ]);

        $this->service->generate($report);
        $report->refresh();

        $content = json_decode(Storage::disk('local')->get($report->file_path), true);

        $this->assertArrayHasKey('report_id', $content);
        $this->assertArrayHasKey('period', $content);
        $this->assertArrayHasKey('start', $content['period']);
        $this->assertArrayHasKey('end', $content['period']);
        $this->assertArrayHasKey('user_id', $content);
        $this->assertArrayHasKey('summary', $content);
        $this->assertArrayHasKey('total_notifications', $content['summary']);
        $this->assertArrayHasKey('by_channel', $content);
        $this->assertArrayHasKey('generated_at', $content);
    }

    public function test_it_updates_status_to_processing_during_generation(): void
    {
        Notification::create([
            'user_id'  => $this->user->id,
            'message'  => 'Test',
            'channel'  => NotificationChannel::EMAIL->value,
            'status'   => NotificationStatus::SENT->value,
            'priority' => NotificationPriority::DEFAULT->value,
        ]);

        $report = NotificationsReport::create([
            'user_id'      => $this->user->id,
            'period_start' => now()->subDays(10),
            'period_end'   => now(),
            'status'       => ReportStatus::PENDING->value,
        ]);

        $this->service->generate($report);
        $report->refresh();

        $this->assertEquals(ReportStatus::COMPLETED->value, $report->status);
        $this->assertNotNull($report->started_at);
        $this->assertNotNull($report->completed_at);
        $this->assertTrue($report->completed_at->gte($report->started_at));
    }
}
