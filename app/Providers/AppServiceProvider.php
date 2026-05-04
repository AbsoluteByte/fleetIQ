<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         | Local / tunnel: PHP often still sees the internal host (e.g. fleetiq.local) while
         | Live Link/ngrok exposes another URL. Laravel would then build route()/url() absolute
         | links against the wrong host. Forcing APP_URL keeps Login links and redirects correct.
         | Switch APP_URL in .env when you move between .local and the public tunnel.
         */
        $root = rtrim((string) config('app.url'), '/');

        if ($root !== '') {
            URL::forceRootUrl($root);

            $scheme = parse_url($root, PHP_URL_SCHEME);
            if (in_array($scheme, ['http', 'https'], true)) {
                URL::forceScheme($scheme);
            }
        }
    }
}
