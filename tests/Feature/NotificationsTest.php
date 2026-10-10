<?php

declare(strict_types=1);

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\Nomination\Model\Nomination;
use Rominas\Audit\Model\AuditLog;
use Rominas\Auth\MagicLink\Model\MagicLinkToken;
use Rominas\Delivery\Actions\DeliveryAction;
use Rominas\Delivery\DeliveryPayloadInterface;
use Rominas\Delivery\DeliveryServiceInterface;
use Rominas\Delivery\SMTP\SmtpMailPayload;
use Rominas\Editions\Actions\TransitionEditionAction;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;
use Rominas\FraudMonitoring\Actions\DetectVotingFraudAction;
use Rominas\Notifications\Notifications\DailyStatistics;
use Rominas\Notifications\Notifications\MemberJoined;
use Rominas\Notifications\Notifications\MemberProposalNeedsReview;
use Rominas\Notifications\Notifications\NewFraudAlerts;
use Rominas\Notifications\Notifications\NominationDeadlineReminder;
use Rominas\Notifications\Notifications\NominationsOpened;
use Rominas\Permissions\Model\Permission;
use Rominas\Roles\Model\Role;
use Rominas\Users\Model\User;
use Rominas\Voting\Model\Ballot;

use function Pest\Laravel\artisan;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travelTo;

/**
 * An admin user whose role grants exactly the given permissions.
 */
function notificationsUserWith(string ...$permissions): User
{
    $role = Role::query()->create(['name' => 'role_' . Str::random(8), 'guard_name' => 'web']);

    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
    }

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function notificationsSuperAdmin(): User
{
    Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('super_admin');

    return $user;
}

/**
 * Store a notification in a user's list directly, without sending anything.
 *
 * @param  array<string, mixed>  $attributes
 */
function notificationsStoreFor(User $user, array $attributes = []): DatabaseNotification
{
    /** @var DatabaseNotification $notification */
    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'member_joined',
        'data' => ['title' => 'Ana Pop joined the academy', 'body' => 'Ana Pop activated their account.', 'url' => '/members/7'],
        ...$attributes,
    ]);

    return $notification;
}

/**
 * Stands in for the SMTP transport, which leaves the process: keeps the payloads it is asked to send.
 */
final class NotificationsRecordingDeliveryService implements DeliveryServiceInterface
{
    /** @var list<SmtpMailPayload> */
    public array $payloads = [];

    public function send(DeliveryPayloadInterface $payload): void
    {
        assert($payload instanceof SmtpMailPayload);

        $this->payloads[] = $payload;
    }
}

/**
 * Route the Delivery module's e-mails to a recorder instead of SMTP, keeping the real payload factories.
 */
function notificationsRecordDeliveries(): NotificationsRecordingDeliveryService
{
    $recorder = new NotificationsRecordingDeliveryService();
    app()->instance(NotificationsRecordingDeliveryService::class, $recorder);

    app()->when(DeliveryAction::class)
        ->needs('$availableDeliveryMethods')
        ->give([
            'email' => [
                'service' => NotificationsRecordingDeliveryService::class,
                'factories' => config('delivery.methods.email.factories'),
            ],
        ]);

    return $recorder;
}

describe('on demand', function (): void {
    it('notifies the users who can review proposals when a member proposes someone', function (): void {
        $reviewer = notificationsUserWith('memberProposals');
        $superAdmin = notificationsSuperAdmin();
        $unrelated = notificationsUserWith('categories');
        $proposer = Member::factory()->active()->create(['name' => 'Radu Ionescu']);
        Sanctum::actingAs($proposer, ['member'], 'member');
        Notification::fake();

        postJson('/api/academy/proposals', ['name' => 'Ioana Munteanu', 'email' => 'ioana@example.test'])
            ->assertStatus(201);

        Notification::assertSentTo(
            $reviewer,
            MemberProposalNeedsReview::class,
            fn(MemberProposalNeedsReview $notification): bool => $notification->proposedName === 'Ioana Munteanu'
                && $notification->proposerName === 'Radu Ionescu',
        );
        Notification::assertSentTo($superAdmin, MemberProposalNeedsReview::class);
        Notification::assertNotSentTo($unrelated, MemberProposalNeedsReview::class);
    });

    it('notifies the users who manage members when an invited member signs in for the first time', function (): void {
        $manager = notificationsUserWith('members');
        $member = Member::factory()->invited()->create(['name' => 'Ana Pop']);
        MagicLinkToken::query()->create([
            'email' => $member->email,
            'guard' => 'member',
            'token' => Hash::make('first-sign-in-token'),
            'created_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]);
        Notification::fake();

        postJson('/api/academy/auth/magic/verify', ['email' => $member->email, 'token' => 'first-sign-in-token'])
            ->assertStatus(200);

        Notification::assertSentTo(
            $manager,
            MemberJoined::class,
            fn(MemberJoined $notification): bool => $notification->memberId === $member->id
                && $notification->memberName === 'Ana Pop',
        );
    });

    it('does not notify again when an already active member signs in', function (): void {
        notificationsUserWith('members');
        $member = Member::factory()->active()->create();
        MagicLinkToken::query()->create([
            'email' => $member->email,
            'guard' => 'member',
            'token' => Hash::make('returning-token'),
            'created_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]);
        Notification::fake();

        postJson('/api/academy/auth/magic/verify', ['email' => $member->email, 'token' => 'returning-token'])
            ->assertStatus(200);

        Notification::assertNothingSent();
    });

    it('notifies fraud monitors once about a new alert, not again when the alert is refreshed', function (): void {
        config([
            'fraud.shared_ip.threshold' => 2,
            'fraud.velocity.threshold' => 100,
            'fraud.identical_ranking.threshold' => 100,
        ]);
        $monitor = notificationsUserWith('fraudMonitoring');
        $admin = notificationsUserWith('members');
        $edition = Edition::factory()->status(EditionStatus::VotingOpen)->create();
        Ballot::factory()->submitted()->count(2)->create([
            'edition_id' => $edition->id,
            'ip_hash' => hash('sha256', '203.0.113.9'),
        ]);
        Notification::fake();

        app(DetectVotingFraudAction::class)->execute($edition);
        app(DetectVotingFraudAction::class)->execute($edition);

        Notification::assertSentToTimes($monitor, NewFraudAlerts::class, 1);
        Notification::assertSentTo(
            $monitor,
            NewFraudAlerts::class,
            fn(NewFraudAlerts $notification): bool => $notification->newAlertCount === 1
                && $notification->editionId === $edition->id,
        );
        Notification::assertNotSentTo($admin, NewFraudAlerts::class);
    });

    it('e-mails invited and active members when nominations open', function (): void {
        $edition = Edition::factory()->status(EditionStatus::Draft)->create([
            'nominations_end_at' => '2026-11-20 21:59:00',
        ]);
        $active = Member::factory()->active()->create();
        $invited = Member::factory()->invited()->create();
        $suspended = Member::factory()->suspended()->create();
        $awaitingInvitation = Member::factory()->awaitingInvitation()->create();
        Notification::fake();

        app(TransitionEditionAction::class)->execute($edition, EditionStatus::NominationsOpen);

        Notification::assertSentTo(
            $active,
            NominationsOpened::class,
            // 21:59 UTC on 20 November is 23:59 in Romania (winter time, UTC+2).
            fn(NominationsOpened $notification): bool => $notification->deadline === '20.11.2026, 23:59',
        );
        Notification::assertSentTo($invited, NominationsOpened::class);
        Notification::assertNotSentTo($suspended, NominationsOpened::class);
        Notification::assertNotSentTo($awaitingInvitation, NominationsOpened::class);
    });

    it('does not e-mail members on any other edition transition', function (): void {
        $edition = Edition::factory()->status(EditionStatus::NominationsOpen)->create();
        Member::factory()->active()->create();
        Notification::fake();

        app(TransitionEditionAction::class)->execute($edition, EditionStatus::NominationsClosed);

        Notification::assertNothingSent();
    });
});

describe('daily statistics', function (): void {
    it('sends the previous Romanian day and the totals to the users who can see reports', function (): void {
        // 05:00 UTC is 08:00 in Romania (summer time, UTC+3); "yesterday" there is 9 October,
        // i.e. 2026-10-08 21:00 UTC up to 2026-10-09 21:00 UTC.
        travelTo('2026-10-10 05:00:00');
        $reporter = notificationsUserWith('reporting');
        $unrelated = notificationsUserWith('members');
        $edition = Edition::factory()->status(EditionStatus::VotingOpen)->create(['name' => 'Romias 2026']);
        Ballot::factory()->submitted()->create(['edition_id' => $edition->id, 'submitted_at' => '2026-10-08 20:30:00']);
        Ballot::factory()->submitted()->create(['edition_id' => $edition->id, 'submitted_at' => '2026-10-08 21:30:00']);
        Ballot::factory()->submitted()->create(['edition_id' => $edition->id, 'submitted_at' => '2026-10-09 10:00:00']);
        Notification::fake();

        artisan('notifications:daily-statistics')->assertSuccessful();

        Notification::assertSentTo(
            $reporter,
            DailyStatistics::class,
            function (DailyStatistics $notification): bool {
                $ballotsSubmitted = collect($notification->rows)->firstWhere('metric', 'Ballots submitted');
                $drafts = collect($notification->rows)->firstWhere('metric', 'Academy nominations in draft');

                return $notification->day === '9 Oct 2026'
                    && $notification->editionName === 'Romias 2026'
                    && $ballotsSubmitted === ['metric' => 'Ballots submitted', 'day' => 2, 'total' => 3]
                    && $drafts === ['metric' => 'Academy nominations in draft', 'day' => null, 'total' => 0];
            },
        );
        Notification::assertNotSentTo($unrelated, DailyStatistics::class);
    });

    it('sends nothing when no edition is in progress', function (): void {
        notificationsUserWith('reporting');
        Edition::factory()->status(EditionStatus::Draft)->create();
        Notification::fake();

        artisan('notifications:daily-statistics')->assertSuccessful();

        Notification::assertNothingSent();
    });
});

describe('nomination deadline reminders', function (): void {
    it('reminds members without submitted nominations three days before the deadline', function (): void {
        // Romania is on 10 October; the deadline is 23:59 on 13 October there — three days left.
        travelTo('2026-10-10 07:00:00');
        $edition = Edition::factory()->status(EditionStatus::NominationsOpen)->create([
            'nominations_end_at' => '2026-10-13 20:59:00',
        ]);
        $notStarted = Member::factory()->active()->create();
        $invited = Member::factory()->invited()->create();
        $drafting = Member::factory()->active()->create();
        Nomination::factory()->create(['member_id' => $drafting->id, 'edition_id' => $edition->id]);
        $submitted = Member::factory()->active()->create();
        Nomination::factory()->submitted()->create(['member_id' => $submitted->id, 'edition_id' => $edition->id]);
        $suspended = Member::factory()->suspended()->create();
        Notification::fake();

        artisan('notifications:nomination-reminders')->assertSuccessful();

        Notification::assertSentTo(
            $notStarted,
            NominationDeadlineReminder::class,
            fn(NominationDeadlineReminder $notification): bool => $notification->daysBefore === 3
                && $notification->deadline === '13.10.2026, 23:59',
        );
        Notification::assertSentTo($invited, NominationDeadlineReminder::class);
        Notification::assertSentTo($drafting, NominationDeadlineReminder::class);
        Notification::assertNotSentTo($submitted, NominationDeadlineReminder::class);
        Notification::assertNotSentTo($suspended, NominationDeadlineReminder::class);
    });

    it('sends no reminder on a day that is not a reminder day', function (): void {
        // Two days left: reminders go out three days and one day before.
        travelTo('2026-10-10 07:00:00');
        Edition::factory()->status(EditionStatus::NominationsOpen)->create([
            'nominations_end_at' => '2026-10-12 20:59:00',
        ]);
        Member::factory()->active()->create();
        Notification::fake();

        artisan('notifications:nomination-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    });

    it('e-mails a member once through the delivery pipeline even when the command runs twice', function (): void {
        travelTo('2026-10-12 07:00:00');
        $edition = Edition::factory()->status(EditionStatus::NominationsOpen)->create([
            'name' => 'Romias 2026',
            'nominations_end_at' => '2026-10-13 20:59:00',
        ]);
        $member = Member::factory()->active()->create(['email' => 'ana@example.test']);
        $sent = notificationsRecordDeliveries();

        artisan('notifications:nomination-reminders')->assertSuccessful();
        artisan('notifications:nomination-reminders')->assertSuccessful();

        expect($sent->payloads)->toHaveCount(1)
            ->and($sent->payloads[0]->recipients)->toBe(['ana@example.test'])
            ->and($sent->payloads[0]->subject)->toBe('Mâine se încheie nominalizările Romias 2026')
            ->and($sent->payloads[0]->body)->toContain('13.10.2026, 23:59')
            ->and($member->notifications()->count())->toBe(1)
            ->and($member->notifications()->first()->data)->toBe(['edition_id' => $edition->id, 'days_before' => 1]);
    });
});

describe('admin list', function (): void {
    it('lists the signed-in user\'s own notifications, newest first', function (): void {
        $user = notificationsUserWith('members');
        $older = notificationsStoreFor($user, ['created_at' => '2026-10-08 09:00:00']);
        $newer = notificationsStoreFor($user, ['created_at' => '2026-10-09 09:00:00', 'read_at' => '2026-10-09 10:00:00']);
        notificationsStoreFor(notificationsUserWith('members'));
        Sanctum::actingAs($user);

        getJson('/api/admin/notifications')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0', [
                'id' => $newer->id,
                'type' => 'member_joined',
                'title' => 'Ana Pop joined the academy',
                'body' => 'Ana Pop activated their account.',
                'url' => '/members/7',
                'read_at' => '2026-10-09T10:00:00.000000Z',
                'created_at' => '2026-10-09T09:00:00.000000Z',
            ])
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('data.1.read_at', null);
    });

    it('lists only unread notifications when asked', function (): void {
        $user = notificationsUserWith('members');
        $unread = notificationsStoreFor($user);
        notificationsStoreFor($user, ['read_at' => '2026-10-09 10:00:00']);
        Sanctum::actingAs($user);

        getJson('/api/admin/notifications?unread=1')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unread->id);
    });

    it('counts the signed-in user\'s unread notifications', function (): void {
        $user = notificationsUserWith('members');
        notificationsStoreFor($user);
        notificationsStoreFor($user);
        notificationsStoreFor($user, ['read_at' => '2026-10-09 10:00:00']);
        notificationsStoreFor(notificationsUserWith('members'));
        Sanctum::actingAs($user);

        getJson('/api/admin/notifications/unread-count')
            ->assertStatus(200)
            ->assertExactJson(['count' => 2]);
    });

    it('marks a notification as read without writing to the audit trail', function (): void {
        travelTo('2026-10-10 12:00:00');
        $user = notificationsUserWith('members');
        $notification = notificationsStoreFor($user);
        Sanctum::actingAs($user);

        postJson("/api/admin/notifications/{$notification->id}/read")
            ->assertStatus(200)
            ->assertJsonPath('data.read_at', '2026-10-10T12:00:00.000000Z');

        expect($notification->refresh()->read_at?->toDateTimeString())->toBe('2026-10-10 12:00:00')
            ->and(AuditLog::query()->count())->toBe(0);
    });

    it('returns 403 when marking another user\'s notification as read', function (): void {
        $notification = notificationsStoreFor(notificationsUserWith('members'));
        Sanctum::actingAs(notificationsUserWith('members'));

        postJson("/api/admin/notifications/{$notification->id}/read")->assertStatus(403);

        expect($notification->refresh()->read_at)->toBeNull();
    });

    it('marks all of the user\'s unread notifications as read and leaves other users\' alone', function (): void {
        $user = notificationsUserWith('members');
        notificationsStoreFor($user);
        notificationsStoreFor($user);
        $someoneElses = notificationsStoreFor(notificationsUserWith('members'));
        Sanctum::actingAs($user);

        postJson('/api/admin/notifications/read-all')
            ->assertStatus(200)
            ->assertExactJson(['count' => 2]);

        expect($user->unreadNotifications()->count())->toBe(0)
            ->and($someoneElses->refresh()->read_at)->toBeNull();
    });

    it('returns 401 for the list without a token', function (): void {
        getJson('/api/admin/notifications')->assertStatus(401);
    });
});
