<?php

namespace App\Http\Middleware;

use App\Providers\InstallerServiceProvider;
use App\Providers\LocalesServiceProvider;
use Carbon\Carbon;
use Closure;
use Cookie;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;

class LocaleSetter
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $code = LocalesServiceProvider::getUserPreferredLocale($request);
        App::setLocale($code);
        Session::put('locale', $code);

//        Custom Carbon language overrides sample
//        $carbonTranslations = Carbon::getTranslator();
//        $carbonTranslations->addResource('array', require base_path('resources/lang/ro/carbon.php'), 'ru');
//        $carbonTranslations->setLocale('ro');

        // Prepping the translation files for frontend usage
        $langPath = app()->langPath().'/'.App::getLocale();
        // Avoiding any 500 errors
        if (! file_exists($langPath.'.json')) {
            $langPath = app()->langPath().'/en';
        }

        if (env('APP_ENV') == 'production') {
            Cache::remember('translations', 30, function () use ($langPath) {
                return file_get_contents($langPath.'.json');
            });
        } else {
            Cache::remember('translations', 30, function () use ($langPath) {
                return file_get_contents($langPath.'.json');
            });
        }

        return $next($request);
    }
}
