<?php

declare(strict_types=1);

namespace Rominas\Academy\Shortlist\Actions;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Rominas\Academy\Shortlist\Model\ShortlistEntry;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Model\Edition;
use Rominas\Users\Model\User;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The edition's shortlists as an XLSX workbook: one sheet per ranked category (in category order), each
 * listing that category's shortlist in position order. A category not generated yet gets a header-only sheet.
 */
class ExportEditionShortlistAction
{
    /** Excel's limit on a sheet name's length. */
    private const SHEET_NAME_MAX_LENGTH = 31;

    public function execute(Edition $edition, User $user): BinaryFileResponse
    {
        $categories = Category::query()
            ->filterByEditionId($edition->id)
            ->ranked()
            ->orderBy('position', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $entriesByCategory = ShortlistEntry::query()
            ->forEdition($edition)
            ->visibleToUser($user)
            ->with('nominee')
            ->orderBy('position')
            ->get()
            ->groupBy('category_id');

        $path = tempnam(sys_get_temp_dir(), 'shortlist_');

        if ($path === false) {
            throw new \RuntimeException('Cannot create export file.');
        }

        $writer = new Writer();

        try {
            $writer->openToFile($path);
            $usedSheetNames = [];

            if ($categories->isEmpty()) {
                $writer->getCurrentSheet()->setName('Shortlist');
            }

            foreach ($categories as $index => $category) {
                $sheet = $index === 0 ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
                $sheet->setName($this->sheetName($category, $usedSheetNames));

                $writer->addRow(Row::fromValues(['Position', 'Nominee', 'Type', 'Academy points']));

                /** @var iterable<ShortlistEntry> $entries */
                $entries = $entriesByCategory->get($category->id, []);

                foreach ($entries as $entry) {
                    /** @var object{name: string}|null $nominee */
                    $nominee = $entry->nominee;

                    $writer->addRow(Row::fromValues([
                        $entry->position,
                        $nominee?->name,
                        $entry->nominee_type->label(),
                        $entry->points,
                    ]));
                }
            }

            $writer->close();
        } catch (\Throwable $e) {
            @unlink($path);
            throw $e;
        }

        return response()
            ->download(
                $path,
                "shortlist-{$edition->slug}-" . now()->format('Y-m-d-His') . '.xlsx',
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ],
            )
            ->deleteFileAfterSend(true);
    }

    /**
     * A sheet name Excel accepts: none of \ / ? * : [ ], at most 31 characters, unique regardless of case.
     *
     * @param  array<string, true>  $usedSheetNames  lower-cased names already taken; the new name is added
     */
    private function sheetName(Category $category, array &$usedSheetNames): string
    {
        $base = trim((string) preg_replace('/[\\\\\/?*:\[\]]+/u', ' ', $category->name));
        $base = trim((string) preg_replace('/\s+/u', ' ', $base), " '");

        if ($base === '') {
            $base = "Category {$category->id}";
        }

        $name = mb_substr($base, 0, self::SHEET_NAME_MAX_LENGTH);

        for ($copy = 2; isset($usedSheetNames[mb_strtolower($name)]); $copy++) {
            $suffix = " ({$copy})";
            $name = rtrim(mb_substr($base, 0, self::SHEET_NAME_MAX_LENGTH - mb_strlen($suffix))) . $suffix;
        }

        $usedSheetNames[mb_strtolower($name)] = true;

        return $name;
    }
}
