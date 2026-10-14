<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Rominas\Academy\Member\Enums\MemberStatus;
use Rominas\Academy\Member\Model\Member;
use Rominas\Audit\Model\AuditLog;
use Rominas\Users\Model\User;

use function Pest\Laravel\post;

function memberImportCsv(string $contents, string $filename = 'members.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($filename, $contents);
}

/**
 * @param  list<list<string>>  $rows
 */
function memberImportXlsx(array $rows): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'members') . '.xlsx';

    $writer = new XlsxWriter();
    $writer->openToFile($path);
    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }
    $writer->close();

    return new UploadedFile($path, 'members.xlsx', null, null, true);
}

function importMembers(UploadedFile $file): TestResponse
{
    return post('/api/admin/members/import', ['file' => $file], ['Accept' => 'application/json']);
}

it('creates a member awaiting invitation for each row of the client\'s spreadsheet', function (): void {
    actingAsSuperAdmin();

    importMembers(memberImportXlsx([
        ['NUME SOCIETATE', 'NUME ADMINISTRATOR', 'E-MAIL PERSOANA DE CONTACT', 'Status'],
        ['Studio Nord SRL ', 'RADU BĂNICIU', 'Radu@Example.test', 'Confirmat'],
        ['Casa de Discuri', 'ȘTEFANIA ANA-MARIA POP', 'stefania@example.test', 'Confirmat'],
    ]))->assertStatus(200)
        ->assertJsonPath('data.created', 2)
        ->assertJsonPath('data.skipped', []);

    $radu = Member::query()->where('email', 'radu@example.test')->firstOrFail();
    expect($radu->name)->toBe('Radu Băniciu')
        ->and($radu->company)->toBe('Studio Nord SRL')
        ->and($radu->status)->toBe(MemberStatus::AwaitingInvitation);

    expect(Member::query()->where('email', 'stefania@example.test')->value('name'))->toBe('Ștefania Ana-Maria Pop');
});

it('reads a CSV separated by commas or by semicolons', function (string $contents): void {
    actingAsSuperAdmin();

    importMembers(memberImportCsv($contents))
        ->assertStatus(200)
        ->assertJsonPath('data.created', 1);

    $member = Member::query()->where('email', 'ana@example.test')->firstOrFail();
    expect($member->name)->toBe('Ana Popescu')
        ->and($member->company)->toBe('Studio Nord');
})->with([
    'commas' => "Name,Email,Company\nAna Popescu,ana@example.test,Studio Nord\n",
    'semicolons with a BOM' => "\xEF\xBB\xBFNUME SOCIETATE;NUME ADMINISTRATOR;E-MAIL PERSOANA DE CONTACT\nStudio Nord;ANA POPESCU;ana@example.test\n",
]);

it('reads a CSV saved by Excel in the Windows Romanian encoding', function (): void {
    actingAsSuperAdmin();

    // Windows-1250 has only the old cedilla letters (Ş, Ţ), not the comma-below ones (Ș, Ț).
    $contents = (string) iconv('UTF-8', 'WINDOWS-1250', "Nume;E-mail\nŞtefan Piţu;stefan@example.test\n");

    importMembers(memberImportCsv($contents))->assertStatus(200)->assertJsonPath('data.created', 1);

    expect(Member::query()->where('email', 'stefan@example.test')->value('name'))->toBe('Ştefan Piţu');
});

it('keeps a name written in mixed case as typed', function (): void {
    actingAsSuperAdmin();

    importMembers(memberImportCsv("Name,Email\nAna de la Vega,ana@example.test\n"))->assertStatus(200);

    expect(Member::query()->where('email', 'ana@example.test')->value('name'))->toBe('Ana de la Vega');
});

it('skips the rows it cannot use, reporting each, and creates the rest', function (): void {
    actingAsSuperAdmin();
    Member::factory()->create(['email' => 'existing@example.test']);

    importMembers(memberImportCsv(implode("\n", [
        'Name,Email',
        'Ana Popescu,ana@example.test',
        'Old Member,EXISTING@example.test',
        'Ana Again,ana@example.test',
        'No Address,',
        ',nameless@example.test',
        'Bad Address,not-an-email',
        ',',
        'Ion Ionescu,ion@example.test',
    ])))->assertStatus(200)
        ->assertJsonPath('data.created', 2)
        ->assertJsonPath('data.skipped', [
            ['row' => 3, 'email' => 'existing@example.test', 'reason' => 'Already a member.'],
            ['row' => 4, 'email' => 'ana@example.test', 'reason' => 'Duplicate e-mail in the file.'],
            ['row' => 5, 'email' => null, 'reason' => 'The e-mail is missing.'],
            ['row' => 6, 'email' => 'nameless@example.test', 'reason' => 'The name is missing.'],
            ['row' => 7, 'email' => 'not-an-email', 'reason' => 'The e-mail is not valid.'],
        ]);

    expect(Member::query()->whereIn('email', ['ana@example.test', 'ion@example.test'])->count())->toBe(2)
        ->and(Member::query()->count())->toBe(3);
});

it('sends nothing to the imported members', function (): void {
    actingAsSuperAdmin();
    Queue::fake();

    importMembers(memberImportCsv("Name,Email\nAna Popescu,ana@example.test\n"))->assertStatus(200);

    Queue::assertNothingPushed();
});

it('records the import in the audit trail without the file', function (): void {
    actingAsSuperAdmin();

    importMembers(memberImportCsv("Name,Email\nAna Popescu,ana@example.test\nNo Address,\n"))->assertStatus(200);

    $entry = AuditLog::query()->where('action', 'api.admin.members.import')->sole();
    expect($entry->context)->toMatchArray([
        'request' => ['file' => '[file]'],
        'imported' => 1,
        'skipped' => 1,
    ]);
});

it('rejects a file without an e-mail column', function (): void {
    actingAsSuperAdmin();

    importMembers(memberImportCsv("Name,Company\nAna Popescu,Studio Nord\n"))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file' => 'The file needs a header row with a name column and an e-mail column.']);

    expect(Member::query()->count())->toBe(0);
});

it('rejects a file that is not a CSV or XLSX', function (): void {
    actingAsSuperAdmin();

    importMembers(UploadedFile::fake()->create('members.pdf', 10, 'application/pdf'))
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('file');
});

it('rejects an XLSX that cannot be read', function (): void {
    actingAsSuperAdmin();

    importMembers(UploadedFile::fake()->createWithContent('members.xlsx', 'not a spreadsheet'))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file' => 'The file could not be read.']);
});

it('forbids a user without the members permission', function (): void {
    Sanctum::actingAs(User::factory()->create());

    importMembers(memberImportCsv("Name,Email\nAna Popescu,ana@example.test\n"))->assertStatus(403);

    expect(Member::query()->count())->toBe(0);
});
