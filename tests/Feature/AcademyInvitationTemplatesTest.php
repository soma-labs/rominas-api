<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Rominas\Academy\Member\Enums\MemberStatus;
use Rominas\Academy\Member\Model\Member;
use Rominas\Auth\MagicLink\Jobs\SendMagicLinkJob;
use Rominas\Delivery\Actions\DeliveryAction;
use Rominas\Delivery\DeliveryPayloadInterface;
use Rominas\Delivery\DeliveryServiceInterface;
use Rominas\Delivery\MailPayload;
use Rominas\Users\Model\User;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * Stands in for the SMTP transport: keeps the invitation payloads it is asked to send.
 */
final class InvitationTemplatesRecordingDeliveryService implements DeliveryServiceInterface
{
    /** @var list<MailPayload> */
    public array $payloads = [];

    public function send(DeliveryPayloadInterface $payload): void
    {
        assert($payload instanceof MailPayload);

        $this->payloads[] = $payload;
    }
}

function invitationTemplatesRecordDeliveries(): InvitationTemplatesRecordingDeliveryService
{
    $recorder = new InvitationTemplatesRecordingDeliveryService();
    app()->instance(InvitationTemplatesRecordingDeliveryService::class, $recorder);
    app()->when(DeliveryAction::class)
        ->needs('$availableDeliveryMethods')
        ->give([
            'email' => [
                'service' => InvitationTemplatesRecordingDeliveryService::class,
                'factories' => config('delivery.methods.email.factories'),
            ],
        ]);

    return $recorder;
}

it('lists the invitation templates, the default first', function (): void {
    actingAsSuperAdmin();

    getJson('/api/admin/members/invitation-templates')
        ->assertStatus(200)
        ->assertExactJson(['data' => [
            ['key' => 'default', 'label' => 'Default (Romanian)'],
            ['key' => 'english', 'label' => 'English'],
        ]]);
});

it('sends each picked member their own invitation in the chosen template and marks them invited', function (): void {
    actingAsSuperAdmin();
    $sent = invitationTemplatesRecordDeliveries();
    $ana = Member::factory()->awaitingInvitation()->create(['name' => 'Ana Popescu', 'email' => 'ana@example.test']);
    $ion = Member::factory()->awaitingInvitation()->create(['name' => 'Ion Ionescu', 'email' => 'ion@example.test']);
    $notPicked = Member::factory()->awaitingInvitation()->create();

    postJson('/api/admin/members/invitations/send', ['member_ids' => [$ana->id, $ion->id], 'template' => 'english'])
        ->assertStatus(200)
        ->assertExactJson(['success' => true, 'invited' => 2]);

    expect($sent->payloads)->toHaveCount(2)
        ->and($sent->payloads[0]->recipients)->toBe(['ana@example.test'])
        ->and($sent->payloads[0]->subject)->toBe('Invitation to The Romanian Music Industry Awards Academy')
        ->and($sent->payloads[0]->body)->toContain('<html lang="en"')
        ->and($sent->payloads[0]->body)->toContain('Hello, Ana Popescu!')
        ->and($sent->payloads[1]->recipients)->toBe(['ion@example.test']);
    expect($ana->refresh()->status)->toBe(MemberStatus::Invited)
        ->and($ana->invited_at)->not->toBeNull();
    expect($notPicked->refresh()->status)->toBe(MemberStatus::AwaitingInvitation);
});

it('sends the default Romanian invitation when an admin invites one member', function (): void {
    actingAsSuperAdmin();
    $sent = invitationTemplatesRecordDeliveries();
    $member = Member::factory()->awaitingInvitation()->create();

    postJson("/api/admin/members/{$member->id}/invite")->assertStatus(200);

    expect($sent->payloads)->toHaveCount(1)
        ->and($sent->payloads[0]->subject)->toBe('Invitație în Academia The Romanian Music Industry Awards')
        ->and($sent->payloads[0]->body)->toContain('<html lang="ro"');
});

it('refuses a member who is not awaiting an invitation with a 422 and sends nothing', function (MemberStatus $status): void {
    actingAsSuperAdmin();
    Bus::fake();
    $awaiting = Member::factory()->awaitingInvitation()->create();
    $other = Member::factory()->create(['status' => $status]);

    postJson('/api/admin/members/invitations/send', ['member_ids' => [$awaiting->id, $other->id], 'template' => 'default'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['member_ids.1' => 'This member is not awaiting an invitation.']);

    Bus::assertNotDispatched(SendMagicLinkJob::class);
    expect($awaiting->refresh()->status)->toBe(MemberStatus::AwaitingInvitation);
})->with([MemberStatus::Invited, MemberStatus::Active, MemberStatus::Suspended]);

it('refuses a member that does not exist with a 422', function (): void {
    actingAsSuperAdmin();
    Bus::fake();

    postJson('/api/admin/members/invitations/send', ['member_ids' => [999999], 'template' => 'default'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['member_ids.0' => 'The selected member does not exist.']);

    Bus::assertNotDispatched(SendMagicLinkJob::class);
});

it('refuses an unknown template with a 422', function (): void {
    actingAsSuperAdmin();
    Bus::fake();
    $member = Member::factory()->awaitingInvitation()->create();

    postJson('/api/admin/members/invitations/send', ['member_ids' => [$member->id], 'template' => 'no-such-template'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['template' => 'The selected template is invalid.']);

    Bus::assertNotDispatched(SendMagicLinkJob::class);
});

it('requires members and a template with a 422', function (): void {
    actingAsSuperAdmin();

    postJson('/api/admin/members/invitations/send', ['member_ids' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'member_ids' => 'The member ids field is required.',
            'template' => 'The template field is required.',
        ]);
});

it('forbids sending picked invitations without the members permission', function (): void {
    Sanctum::actingAs(User::factory()->create());
    $member = Member::factory()->awaitingInvitation()->create();

    postJson('/api/admin/members/invitations/send', ['member_ids' => [$member->id], 'template' => 'default'])
        ->assertStatus(403);
});

it('lists only the members with the given status', function (): void {
    actingAsSuperAdmin();
    $awaiting = Member::factory()->awaitingInvitation()->create();
    Member::factory()->invited()->create();
    Member::factory()->active()->create();

    getJson('/api/admin/members?status=awaiting_invitation')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $awaiting->id);
});

it('refuses an unknown member status filter with a 422', function (): void {
    actingAsSuperAdmin();

    getJson('/api/admin/members?status=nonsense')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status' => 'The selected status is invalid.']);
});
