<?php

declare(strict_types=1);

namespace App\DTO\Notifications;

readonly class ListNotificationsParams
{
    public function __construct(
        public int $userId,
        public ?string $status = null,
        public ?string $channel = null,
        public int $page = 1,
        public int $perPage = 15,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            userId: $data['user_id'],
            status: $data['status'] ?: null,
            channel: $data['channel'] ?: null,
            page: $data['page'] ?? 1,
            perPage: $data['per_page'] ?? 15,
        );
    }
}
