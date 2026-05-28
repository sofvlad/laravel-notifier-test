<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Notification;

use App\Enums\NotificationStatus;
use App\Services\Notifications\ChannelManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $channels = app(ChannelManager::class)->getAvailableChannels();

        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'status'  => ['nullable', 'string', Rule::enum(NotificationStatus::class)],
            'channel' => ['nullable', 'string', Rule::in($channels)],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'User ID is required',
            'user_id.integer'  => 'User ID must be an integer',
            'user_id.exists'   => 'The selected user does not exist',
            'status.enum'      => 'Invalid status value',
            'channel.in'       => 'Invalid channel value',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'user шв',
            'status'  => 'status',
            'channel' => 'channel',
        ];
    }
}
