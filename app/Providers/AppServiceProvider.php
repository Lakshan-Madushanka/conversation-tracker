<?php

namespace App\Providers;

use App\Support\LearnerDay;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One learner's clock for the whole process, so every piece of the
        // app reads the same moment and tests can pin it in one place.
        $this->app->singleton(LearnerDay::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureInertiaExceptions();
    }

    /**
     * A missing page renders a friendly Inertia error page instead of the
     * framework's default, keeping the 404 status and the app's layout.
     */
    protected function configureInertiaExceptions(): void
    {
        Inertia::handleExceptionsUsing(function ($exceptionResponse) {
            if (
                $exceptionResponse->exception instanceof NotFoundHttpException
                && ! $exceptionResponse->request->expectsJson()
            ) {
                return $exceptionResponse->render('errors/404');
            }

            return null;
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
