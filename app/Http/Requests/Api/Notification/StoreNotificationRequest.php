<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Notification;

use App\Enums\NotificationPriority;
use App\Services\Notifications\ChannelManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $channels = app(ChannelManager::class)->getAvailableChannels();

        return [
            'user_id'    => ['required_without:user_ids', 'integer', 'exists:users,id'],
            'user_ids'   => ['required_without:user_id', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'message'    => ['required', 'string', 'max:500'],
            'channel'    => ['required', 'string', Rule::in($channels)],
            'priority'   => ['required', 'string', Rule::in(NotificationPriority::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required_without'  => 'Either user_ids or user_id must be provided',
            'user_id.integer'           => 'User ID must be an integer',
            'user_id.exists'            => 'The selected user does not exist',
            'user_ids.required_without' => 'Either user_ids or user_id must be provided',
            'user_ids.array'            => 'User IDs must be an array',
            'user_ids.min'              => 'User IDs array must have at least one user',
            'user_ids.*.integer'        => 'Each user ID must be an integer',
            'user_ids.*.exists'         => 'One or more selected users do not exist',
            'message.required'          => 'Message is required',
            'message.string'            => 'Message must be a string',
            'message.max'               => 'Message may not be greater than 500 characters',
            'channel.required'          => 'Channel is required',
            'channel.string'            => 'Channel must be a string',
            'channel.in'                => 'Invalid channel value',
            'priority.in'               => 'Priority must be either critical or default',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id'  => 'user id',
            'user_ids' => 'user ids',
            'message'  => 'message',
            'channel'  => 'channel',
            'priority' => 'priority',
        ];
    }
}
