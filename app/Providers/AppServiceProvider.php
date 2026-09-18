<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Rominas\Catalog\Enums\NomineeType;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

        $this->configureDeliveryServices();
        $this->configureScoring();
        $this->configureFraudMonitoring();
        $this->configureAcademy();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Models live under `Rominas\<Module>\Model\<Name>` (not `App\Models`), so map each
        // to its flat factory `Database\Factories\<Name>Factory`. Factories set `$model`.
        Factory::guessFactoryNamesUsing(
            static fn(string $modelName): string => 'Database\\Factories\\' . class_basename($modelName) . 'Factory',
        );

        $this->configureMorphMap();
        $this->configureRateLimiting();
    }

    /**
     * Map polymorphic `nominee` relations to the stable NomineeType slugs (`artist`, `band`, …)
     * instead of class FQNs, keeping DB rows and API payloads decoupled from PHP namespaces —
     * consistent with NomineeType's slug-backed design. Uses morphMap (merge), NOT enforceMorphMap:
     * only the Catalog nominees are aliased; other morphs (Sanctum tokenable, Spatie model_has_roles)
     * keep storing their class name as before.
     */
    private function configureMorphMap(): void
    {
        $map = [];

        foreach (NomineeType::cases() as $type) {
            $map[$type->value] = $type->modelClass();
        }

        Relation::morphMap($map);
    }

    /**
     * Supply the Scoring engine with its display precision (config/scoring.php). The class weights are
     * per-edition (`editions.academy_vote_weight` / `public_vote_weight`) and passed at call time.
     */
    private function configureScoring(): void
    {
        $this->app->when(\Rominas\Scoring\Support\ScoreCalculator::class)
            ->needs('$precision')
            ->give(static fn(): int => (int) config('scoring.precision'));
    }

    /**
     * Inject the enabled fraud detectors (config/fraud.php `enabled_detectors`) into the detection
     * action, in configured order. Removing a detector from the config disables it.
     */
    private function configureFraudMonitoring(): void
    {
        $this->app->when(\Rominas\FraudMonitoring\Actions\DetectVotingFraudAction::class)
            ->needs('$detectors')
            ->give(function (): array {
                /** @var list<class-string<\Rominas\FraudMonitoring\Detectors\FraudDetector>> $classes */
                $classes = config('fraud.enabled_detectors', []);

                $detectors = [];

                foreach ($classes as $class) {
                    $detectors[] = $this->app->make($class);
                }

                return $detectors;
            });
    }

    /**
     * Supply the member-proposal creation action with its per-member lifetime cap
     * (config/academy.php `max_proposals_per_member`).
     */
    private function configureAcademy(): void
    {
        $this->app->when(\Rominas\Academy\MemberProposal\Actions\CreateMemberProposalAction::class)
            ->needs('$maxProposalsPerMember')
            ->give(static fn(): int => (int) config('academy.max_proposals_per_member'));
    }

    /**
     * Wire the Delivery module's transactional-email pipeline (SMTP transport).
     */
    private function configureDeliveryServices(): void
    {
        $this->app->when(\Rominas\Delivery\Actions\DeliveryAction::class)
            ->needs('$availableDeliveryMethods')
            ->give(config('delivery.methods'));

        $this->configureSmtpMail();

        // Brevo transport — to switch, comment out configureSmtpMail() above, uncomment
        // configureBrevoMail() below, and swap the 'email' block in config/delivery.php.
        // $this->configureBrevoMail();
    }

    private function configureSmtpMail(): void
    {
        $this->app->bind(
            \Rominas\Delivery\MailServiceInterface::class,
            \Rominas\Delivery\SMTP\SmtpMailService::class,
        );

        $this->app->when(\Rominas\Delivery\SMTP\SmtpMailService::class)
            ->needs('$host')
            ->give(config('mail.mailers.smtp.host'));

        $this->app->when(\Rominas\Delivery\SMTP\SmtpMailService::class)
            ->needs('$port')
            ->give(config('mail.mailers.smtp.port'));

        $this->app->when(\Rominas\Delivery\SMTP\SmtpMailService::class)
            ->needs('$username')
            ->give(config('mail.mailers.smtp.username'));

        $this->app->when(\Rominas\Delivery\SMTP\SmtpMailService::class)
            ->needs('$password')
            ->give(config('mail.mailers.smtp.password'));

        $this->app->when(\Rominas\Delivery\SMTP\SmtpMailService::class)
            ->needs('$encryption')
            ->give(config('mail.mailers.smtp.scheme') ?? 'tls');
    }

    // Brevo transactional-email transport — requires getbrevo/brevo-php (installed) plus
    // BREVO_* credentials in config/services.php. Enable via configureDeliveryServices().
    // private function configureBrevoMail(): void
    // {
    //     $this->app->bind(
    //         \Rominas\Delivery\MailServiceInterface::class,
    //         \Rominas\Delivery\Brevo\BrevoMailService::class,
    //     );
    //
    //     $this->app->when(\Rominas\Delivery\Brevo\BrevoMailService::class)
    //         ->needs('$fromEmail')->give(config('services.brevo.from_email'));
    //     $this->app->when(\Rominas\Delivery\Brevo\BrevoMailService::class)
    //         ->needs('$fromName')->give(config('services.brevo.from_name'));
    //     $this->app->when(\Rominas\Delivery\Brevo\BrevoMailService::class)
    //         ->needs('$apiKey')->give(config('services.brevo.api_key'));
    // }

    /**
     * Throttle the passwordless magic-link / OTP endpoints per email + IP — each request
     * sends an email, so it must resist abuse.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('magic-request', static fn(Request $request): Limit => Limit::perMinute(5)
            ->by((string) $request->input('email') . '|' . $request->ip()));

        RateLimiter::for('otp-request', static fn(Request $request): Limit => Limit::perMinute(5)
            ->by((string) $request->input('email') . '|' . $request->ip()));

        RateLimiter::for('otp-login', static fn(Request $request): Limit => Limit::perMinute(10)
            ->by((string) $request->input('email') . '|' . $request->ip()));

        // Public voting-link requests each send an email — throttle per email + IP.
        RateLimiter::for('voting-request', static fn(Request $request): Limit => Limit::perMinute(5)
            ->by((string) $request->input('email') . '|' . $request->ip()));
    }
}
