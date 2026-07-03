<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Blade;
use App\Helpers\CollectionHelper;
use App\Helpers\StringHelper;
use App\Helpers\MoneyHelper;

class UtilityServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind utilities for dependency injection if needed
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 1. Register custom Support Collection macros
        Collection::macro('paginate', function (int $perPage = 15, ?int $page = null, array $options = []) {
            return CollectionHelper::paginate($this, $perPage, $page, $options);
        });

        Collection::macro('distribute', function (int $numberOfBuckets) {
            return CollectionHelper::distribute($this, $numberOfBuckets);
        });

        // 2. Register custom Str macros
        Str::macro('maskEmail', function (string $email) {
            return StringHelper::maskEmail($email);
        });

        Str::macro('maskPhone', function (string $phone) {
            return StringHelper::maskPhone($phone);
        });

        // 3. Register custom Blade directives for formatting money
        Blade::directive('money', function (string $expression) {
            return "<?php echo \App\Helpers\MoneyHelper::format($expression); ?>";
        });
    }
}
