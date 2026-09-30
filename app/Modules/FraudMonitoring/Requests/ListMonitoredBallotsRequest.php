<?php

declare(strict_types=1);

namespace Rominas\FraudMonitoring\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Rominas\FraudMonitoring\DataTransferObjects\MonitoredBallotFilters;

/**
 * Optional filters for the fraud-review ballot list. Authorization is done by the route `can:` middleware.
 */
class ListMonitoredBallotsRequest extends FormRequest
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
            'ip_hash' => 'nullable|string|max:255',
            'invalidated' => 'nullable|boolean',
        ];
    }

    public function filters(): MonitoredBallotFilters
    {
        return new MonitoredBallotFilters(
            ipHash: $this->filled('ip_hash') ? $this->string('ip_hash')->toString() : null,
            invalidated: $this->filled('invalidated') ? $this->boolean('invalidated') : null,
        );
    }
}
