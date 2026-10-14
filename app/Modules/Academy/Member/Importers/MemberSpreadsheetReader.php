<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\Importers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Exception\OpenSpoutException;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Rominas\Academy\Member\DataTransferObjects\MemberImportRowData;

/**
 * Reads an uploaded member list (CSV or XLSX, first sheet only). The columns are found by their header,
 * compared without case, spaces or diacritics, so the client's own sheet ("NUME ADMINISTRATOR",
 * "E-MAIL PERSOANA DE CONTACT", "NUME SOCIETATE") works as it is; any other column is ignored.
 */
class MemberSpreadsheetReader
{
    /**
     * What a CSV that isn't UTF-8 is read as: Excel's "CSV" (not "CSV UTF-8") on a Romanian Windows.
     */
    private const LEGACY_CSV_ENCODING = 'Windows-1250';

    /**
     * Keywords per field, checked in this order: the e-mail first, since its header usually also
     * contains a name word ("e-mail persoana de contact").
     *
     * @var array<string, list<string>>
     */
    private const HEADER_KEYWORDS = [
        'email' => ['mail'],
        'company' => ['societate', 'firma', 'companie', 'company'],
        'position' => ['functie', 'position'],
        'phone' => ['telefon', 'phone'],
        'name' => ['nume', 'name', 'administrator'],
    ];

    /**
     * @return list<MemberImportRowData>
     *
     * @throws ValidationException when the file can't be read or has no name or e-mail column
     */
    public function read(UploadedFile $file): array
    {
        $path = $file->getPathname();

        $reader = strtolower($file->getClientOriginalExtension()) === 'xlsx'
            ? new XlsxReader(new XlsxOptions(SHOULD_PRESERVE_EMPTY_ROWS: true))
            : $this->csvReader($path);

        try {
            return $this->rows($reader, $path);
        } catch (OpenSpoutException) {
            throw ValidationException::withMessages(['file' => __('The file could not be read.')]);
        } finally {
            $reader->close();
        }
    }

    /**
     * @return list<MemberImportRowData>
     */
    private function rows(CsvReader|XlsxReader $reader, string $path): array
    {
        $reader->open($path);

        $columns = null;
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $index => $row) {
                $cells = $this->cells($row);

                if ($columns === null) {
                    $columns = $this->columns($cells);

                    continue;
                }

                $values = [];
                foreach ($columns as $field => $position) {
                    $values[$field] = $cells[$position] ?? null;
                }

                if (array_filter($values, fn(?string $value): bool => $value !== null) === []) {
                    continue;
                }

                $rows[] = new MemberImportRowData(
                    rowNumber: $index,
                    name: $values['name'],
                    email: $values['email'],
                    position: $values['position'] ?? null,
                    company: $values['company'] ?? null,
                    phone: $values['phone'] ?? null,
                );
            }

            break;
        }

        if ($columns === null) {
            $this->throwMissingColumns();
        }

        return $rows;
    }

    /**
     * Maps each field to the position of its column; the first matching column wins.
     *
     * @param  list<?string>  $headers
     * @return array<string, int>
     */
    private function columns(array $headers): array
    {
        $columns = [];

        foreach ($headers as $position => $header) {
            if ($header === null) {
                continue;
            }

            $header = Str::lower(Str::ascii($header));

            foreach (self::HEADER_KEYWORDS as $field => $keywords) {
                if (Str::contains($header, $keywords)) {
                    $columns[$field] ??= $position;

                    break;
                }
            }
        }

        if (! isset($columns['name'], $columns['email'])) {
            $this->throwMissingColumns();
        }

        return $columns;
    }

    /**
     * The row's cells as trimmed strings, a blank cell as null.
     *
     * @return list<?string>
     */
    private function cells(Row $row): array
    {
        return array_map(function (mixed $value): ?string {
            if (! is_scalar($value)) {
                return null;
            }

            $value = trim((string) $value);

            return $value === '' ? null : $value;
        }, $row->toArray());
    }

    private function csvReader(string $path): CsvReader
    {
        $contents = (string) file_get_contents($path);

        return new CsvReader(new CsvOptions(
            SHOULD_PRESERVE_EMPTY_ROWS: true,
            FIELD_DELIMITER: $this->csvDelimiter($contents),
            ENCODING: mb_check_encoding($contents, 'UTF-8') ? 'UTF-8' : self::LEGACY_CSV_ENCODING,
        ));
    }

    /**
     * Excel saves a CSV with ";" in a Romanian locale and with "," elsewhere; whichever the header
     * line has more of wins.
     */
    private function csvDelimiter(string $contents): string
    {
        $line = strtok($contents, "\r\n");

        if ($line === false) {
            return ',';
        }

        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }

    private function throwMissingColumns(): never
    {
        throw ValidationException::withMessages([
            'file' => __('The file needs a header row with a name column and an e-mail column.'),
        ]);
    }
}
