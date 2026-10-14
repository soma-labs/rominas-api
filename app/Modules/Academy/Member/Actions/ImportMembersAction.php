<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Rominas\Academy\Member\DataTransferObjects\MemberData;
use Rominas\Academy\Member\DataTransferObjects\MemberImportResultData;
use Rominas\Academy\Member\DataTransferObjects\MemberImportRowData;
use Rominas\Academy\Member\DataTransferObjects\MemberImportSkippedRowData;
use Rominas\Academy\Member\Importers\MemberSpreadsheetReader;
use Rominas\Academy\Member\Model\Member;

/**
 * Creates one member awaiting invitation per row of an uploaded list. A row that can't be used (already a
 * member, a repeat within the file, a missing name, an invalid e-mail) is skipped and reported, so the
 * same file can be uploaded again safely. Nothing is sent: invitations stay a separate, manual step.
 */
class ImportMembersAction
{
    public function __construct(
        private readonly MemberSpreadsheetReader $reader,
        private readonly CreateMemberAction $createMember,
    ) {}

    /**
     * @throws ValidationException when the file can't be read or lacks a name or e-mail column
     */
    public function execute(UploadedFile $file): MemberImportResultData
    {
        $created = 0;
        $skipped = [];
        $importedEmails = [];

        foreach ($this->reader->read($file) as $row) {
            $row = $this->normalize($row);

            if ($row->email !== null && in_array($row->email, $importedEmails, true)) {
                $skipped[] = new MemberImportSkippedRowData($row->rowNumber, $row->email, __('Duplicate e-mail in the file.'));

                continue;
            }

            $reason = $this->rejectionReason($row);

            if ($reason !== null) {
                $skipped[] = new MemberImportSkippedRowData($row->rowNumber, $row->email, $reason);

                continue;
            }

            /** @var string $name */
            $name = $row->name;
            /** @var string $email */
            $email = $row->email;

            $this->createMember->execute(new MemberData(
                name: $name,
                email: $email,
                position: $row->position,
                company: $row->company,
                phone: $row->phone,
            ));

            $importedEmails[] = $email;
            $created++;
        }

        Context::add('audit', ['imported' => $created, 'skipped' => count($skipped)]);

        return new MemberImportResultData($created, $skipped);
    }

    /**
     * Lowercases the e-mail and title-cases a name typed all in capitals ("RADU BARON" → "Radu Baron");
     * a name in mixed case is kept as typed.
     */
    private function normalize(MemberImportRowData $row): MemberImportRowData
    {
        $name = $row->name;

        if ($name !== null && mb_strtoupper($name) === $name) {
            $name = mb_convert_case(mb_strtolower($name), MB_CASE_TITLE);
        }

        return new MemberImportRowData(
            rowNumber: $row->rowNumber,
            name: $name,
            email: $row->email === null ? null : mb_strtolower($row->email),
            position: $row->position,
            company: $row->company,
            phone: $row->phone,
        );
    }

    /**
     * The same rules as creating a member by hand, with the reason worded for a row of the file.
     */
    private function rejectionReason(MemberImportRowData $row): ?string
    {
        $validator = Validator::make(
            [
                'name' => $row->name,
                'email' => $row->email,
                'position' => $row->position,
                'company' => $row->company,
                'phone' => $row->phone,
            ],
            [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:' . Member::class . ',email',
                'position' => 'nullable|string|max:255',
                'company' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:255',
            ],
            [
                'name.required' => __('The name is missing.'),
                'email.required' => __('The e-mail is missing.'),
                'email.email' => __('The e-mail is not valid.'),
                'email.unique' => __('Already a member.'),
            ],
        );

        return $validator->fails() ? $validator->errors()->first() : null;
    }
}
