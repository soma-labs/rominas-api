<?php

declare(strict_types=1);

namespace Rominas\Notifications\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters for the admin's own notification list.
 */
class ListNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'unread' => 'sometimes|boolean',
            'page' => 'sometimes|integer|min:1',
        ];
    }
}
