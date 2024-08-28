<?php

namespace App\Providers;

use App\Interfaces\EcommerceScraperInterface;
use App\Services\EcommerceScraperService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

use Illuminate\Support\Facades\Response;
use Illuminate\Auth\Notifications\ResetPassword;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EcommerceScraperInterface::class, EcommerceScraperService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
            // URL::forceRootUrl(config('app.url'));
        }

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        Sanctum::usePersonalAccessTokenModel(\Laravel\Sanctum\PersonalAccessToken::class);

        Response::macro('notFound', function ($message = 'Resource not found') {
            return Response::make([
                'message' => $message
            ], 404);
        });

        Response::macro('success', function ($data, $message = 'Data returned', $httpCode = 200) {
            return Response::make([
                'message' => $message,
                'data' => $data,
            ], $httpCode);
        });

        Response::macro('unprocessable', function ($data, $message = 'Unprocessable Entity', $httpCode = 422) {
            return Response::make([
                'message' => $message,
                'data' => $data,
            ], $httpCode);
        });

        Response::macro('error', function ($data, $message = 'Something went wrong', $httpCode = 500, $success = false) {
            return Response::make([
                'message' => $message,
                'data' => $data,
            ], $httpCode);
        });
    }
}
