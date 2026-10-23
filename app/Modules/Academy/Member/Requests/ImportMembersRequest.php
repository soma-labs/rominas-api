<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The client's extension picks the reader; the content check also allows `txt` (how a CSV is often
     * detected) and `zip` (how some systems detect an XLSX). A file that isn't what it claims fails to read.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => 'required|file|max:2048|extensions:csv,xlsx|mimes:csv,txt,xlsx,zip',
            'is_founding_member' => 'sometimes|boolean',
        ];
    }
}
