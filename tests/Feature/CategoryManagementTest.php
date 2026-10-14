<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Rominas\Catalog\Enums\NomineeType;
use Rominas\Categories\Enums\CategoryKind;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Model\Edition;
use Rominas\Users\Model\User;

use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

it('lets a super_admin create a typed category under an edition', function (): void {
    actingAsSuperAdmin();
    $edition = Edition::factory()->create();

    postJson('/api/admin/categories', [
        'edition_id' => $edition->id,
        'name' => 'Best Album',
        'nominee_type' => NomineeType::Album->value,
        'position' => 1,
    ])->assertStatus(201)
        ->assertJsonPath('data.nominee_type', 'album')
        ->assertJsonPath('data.slug', 'best-album');

    expect(Category::query()->where('edition_id', $edition->id)->count())->toBe(1);
});

it('rejects an invalid nominee type', function (): void {
    actingAsSuperAdmin();
    $edition = Edition::factory()->create();

    postJson('/api/admin/categories', [
        'edition_id' => $edition->id,
        'name' => 'Best Whatever',
        'nominee_type' => 'orchestra',
    ])->assertStatus(422);
});

it('enforces unique category name within an edition', function (): void {
    actingAsSuperAdmin();
    $edition = Edition::factory()->create();
    Category::factory()->for($edition)->create(['name' => 'Best Song']);

    postJson('/api/admin/categories', [
        'edition_id' => $edition->id,
        'name' => 'Best Song',
        'nominee_type' => NomineeType::Song->value,
    ])->assertStatus(422);
});

it('filters categories by edition', function (): void {
    actingAsSuperAdmin();
    $a = Edition::factory()->create();
    $b = Edition::factory()->archived()->create();
    Category::factory()->count(2)->for($a)->create();
    Category::factory()->count(3)->for($b)->create();

    getJson('/api/admin/categories?editionId=' . $a->id)
        ->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

it('forbids categories management without permission', function (): void {
    $edition = Edition::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    postJson('/api/admin/categories', [
        'edition_id' => $edition->id,
        'name' => 'Best Album',
        'nominee_type' => NomineeType::Album->value,
    ])->assertStatus(403);
});

it('creates a category that is not ranked without a nominee type', function (CategoryKind $kind): void {
    actingAsSuperAdmin();
    $edition = Edition::factory()->create();

    postJson('/api/admin/categories', [
        'edition_id' => $edition->id,
        'name' => 'Lifetime Achievement Award',
        'kind' => $kind->value,
    ])->assertStatus(201)
        ->assertJsonPath('data.kind', $kind->value)
        ->assertJsonPath('data.nominee_type', null);
})->with([CategoryKind::ProposeOrVote, CategoryKind::Appointed]);

it('defaults a new category to ranked', function (): void {
    actingAsSuperAdmin();
    $edition = Edition::factory()->create();

    postJson('/api/admin/categories', [
        'edition_id' => $edition->id,
        'name' => 'Best Album',
        'nominee_type' => NomineeType::Album->value,
    ])->assertStatus(201)
        ->assertJsonPath('data.kind', 'ranked');
});

it('rejects a nominee type on a category that is not ranked', function (): void {
    actingAsSuperAdmin();
    $edition = Edition::factory()->create();

    postJson('/api/admin/categories', [
        'edition_id' => $edition->id,
        'name' => 'Critics Choice',
        'kind' => CategoryKind::Appointed->value,
        'nominee_type' => NomineeType::Album->value,
    ])->assertStatus(422)
        ->assertJsonValidationErrors('nominee_type');
});

it('requires a nominee type on a ranked category', function (): void {
    actingAsSuperAdmin();
    $edition = Edition::factory()->create();

    postJson('/api/admin/categories', [
        'edition_id' => $edition->id,
        'name' => 'Best Album',
        'kind' => CategoryKind::Ranked->value,
    ])->assertStatus(422)
        ->assertJsonValidationErrors('nominee_type');
});

it('rejects an unknown kind', function (): void {
    actingAsSuperAdmin();
    $edition = Edition::factory()->create();

    postJson('/api/admin/categories', [
        'edition_id' => $edition->id,
        'name' => 'Best Whatever',
        'kind' => 'lottery',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('kind');
});

it('keeps the kind of a category when it is updated', function (): void {
    actingAsSuperAdmin();
    $category = Category::factory()->proposeOrVote()->create();

    patchJson("/api/admin/categories/{$category->id}", [
        'name' => 'Renamed',
        'kind' => CategoryKind::Ranked->value,
    ])->assertStatus(200)
        ->assertJsonPath('data.name', 'Renamed')
        ->assertJsonPath('data.kind', 'propose_or_vote');
});

it('filters categories by kind', function (): void {
    actingAsSuperAdmin();
    $edition = Edition::factory()->create();
    Category::factory()->count(2)->for($edition)->create();
    Category::factory()->for($edition)->appointed()->create();

    getJson("/api/admin/categories?editionId={$edition->id}&kind=ranked")
        ->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

it('stores a category\'s input placeholder and eligibility criteria', function (): void {
    actingAsSuperAdmin();
    $edition = Edition::factory()->create();
    $criteria = '<h2>Who can be nominated</h2><p>Artists with a release in <a href="https://example.com">the season</a>.</p><ul><li>One</li></ul>';

    postJson('/api/admin/categories', [
        'edition_id' => $edition->id,
        'name' => 'Best Album',
        'nominee_type' => NomineeType::Album->value,
        'placeholder' => 'Album title',
        'eligibility_criteria' => $criteria,
    ])->assertStatus(201)
        ->assertJsonPath('data.placeholder', 'Album title')
        ->assertJsonPath(
            'data.eligibility_criteria',
            '<h2>Who can be nominated</h2><p>Artists with a release in <a href="https://example.com" target="_blank" rel="noopener noreferrer">the season</a>.</p><ul><li>One</li></ul>',
        );
});

it('clears a category\'s placeholder and eligibility criteria on update', function (): void {
    actingAsSuperAdmin();
    $category = Category::factory()->create([
        'placeholder' => 'Artist name',
        'eligibility_criteria' => '<p>Rules</p>',
    ]);

    patchJson("/api/admin/categories/{$category->id}", [
        'name' => $category->name,
        'nominee_type' => $category->nominee_type?->value,
        'placeholder' => null,
        'eligibility_criteria' => '<p></p>',
    ])->assertStatus(200)
        ->assertJsonPath('data.placeholder', null)
        ->assertJsonPath('data.eligibility_criteria', null);
});

it('strips from the eligibility criteria what the editor does not produce', function (string $input, string $expected): void {
    actingAsSuperAdmin();
    $category = Category::factory()->create();

    patchJson("/api/admin/categories/{$category->id}", [
        'name' => $category->name,
        'nominee_type' => $category->nominee_type?->value,
        'eligibility_criteria' => $input,
    ])->assertStatus(200)
        ->assertJsonPath('data.eligibility_criteria', $expected);
})->with([
    'script' => ['<p>Rules</p><script>alert(1)</script>', '<p>Rules</p>'],
    'event handler' => ['<p onclick="alert(1)">Rules</p>', '<p>Rules</p>'],
    'javascript link' => ['<p><a href="javascript:alert(1)">Rules</a></p>', '<p><a target="_blank" rel="noopener noreferrer">Rules</a></p>'],
    'image' => ['<p>Rules<img src="https://example.com/x.png"></p>', '<p>Rules</p>'],
    'other elements keep their text' => ['<h1>Title</h1><p><strong>Rules</strong></p>', 'Title<p>Rules</p>'],
]);

it('rejects a placeholder longer than 255 characters', function (): void {
    actingAsSuperAdmin();
    $category = Category::factory()->create();

    patchJson("/api/admin/categories/{$category->id}", [
        'name' => $category->name,
        'nominee_type' => $category->nominee_type?->value,
        'placeholder' => str_repeat('a', 256),
    ])->assertStatus(422)
        ->assertJsonValidationErrors('placeholder');
});
