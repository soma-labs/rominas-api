<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\Actions;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Rominas\Academy\Member\QueryBuilders\MemberQueryBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportMembersAction
{
    public function execute(MemberQueryBuilder $query): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'members_');

        if ($path === false) {
            throw new \RuntimeException('Cannot create export file.');
        }

        $writer = new Writer();

        try {
            $writer->openToFile($path);

            $writer->addRow(Row::fromValues([
                'ID',
                'Name',
                'Email',
                'Position',
                'Company',
                'Phone',
                'Status',
                'Founding member',
                'Email verified at',
                'Invited at',
                'Activated at',
                'Created at',
                'Updated at',
                'Started nominations',
                'Submitted nominations',
                'Submitted member proposals',
            ]));

            foreach ($query->lazy(500) as $member) {
                $writer->addRow(Row::fromValues([
                    $member->id,
                    $member->name,
                    $member->email,
                    $member->position,
                    $member->company,
                    $member->phone,
                    $member->status->value,
                    $this->label($member->is_founding_member),
                    $member->email_verified_at?->format('Y-m-d H:i:s'),
                    $member->invited_at?->format('Y-m-d H:i:s'),
                    $member->activated_at?->format('Y-m-d H:i:s'),
                    $member->created_at?->format('Y-m-d H:i:s'),
                    $member->updated_at?->format('Y-m-d H:i:s'),
                    $this->label($member->getAttribute('has_started_nominations')),
                    $this->label($member->getAttribute('has_submitted_nominations')),
                    $this->label($member->getAttribute('has_submitted_member_proposals')),
                ]));
            }

            $writer->close();
        } catch (\Throwable $e) {
            @unlink($path);
            throw $e;
        }

        return response()
            ->download(
                $path,
                'members-' . now()->format('Y-m-d-His') . '.xlsx',
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ],
            )
            ->deleteFileAfterSend(true);
    }

    private function label(mixed $value): string
    {
        return $value === null
            ? 'No'
            : ((bool) $value ? 'Yes' : 'No');
    }
}
