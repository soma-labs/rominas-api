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
