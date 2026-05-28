<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Notification;

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
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'message' => ['required', 'string', 'max:500'],
            'channel' => ['required', 'string', Rule::in($channels)],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'User ID is required',
            'user_id.integer'  => 'User ID must be an integer',
            'user_id.exists'   => 'The selected user does not exist',
            'message.required' => 'Message is required',
            'message.string'   => 'Message must be a string',
            'message.max'      => 'Message may not be greater than 500 characters',
            'channel.required' => 'Channel is required',
            'channel.string'   => 'Channel must be a string',
            'channel.in'       => 'Invalid channel value',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'user id',
            'message' => 'message',
            'channel' => 'channel',
        ];
    }
}
