<?php

namespace App\Providers;

use App\Http\Middleware\EnsureTenantActive;
use App\Http\Middleware\RequireTenant;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SetLocale;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\PlatformPanelProvider;
use App\Support\CurrentTenant;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class BookingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrentTenant::class);

        $this->app->register(PlatformPanelProvider::class);
        $this->app->register(AdminPanelProvider::class);
    }

    public function boot(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('tenant.resolve', ResolveTenant::class);
        $router->aliasMiddleware('tenant.active', EnsureTenantActive::class);
        $router->aliasMiddleware('locale', SetLocale::class);
        $router->aliasMiddleware('tenant.required', RequireTenant::class);

        // Permintaan Livewire (/livewire/update) harus mengenali tenant dan bahasa yang sama dengan halaman asalnya.
        Livewire::addPersistentMiddleware([
            ResolveTenant::class,
            EnsureTenantActive::class,
            SetLocale::class,
        ]);
    }
}
