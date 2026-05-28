<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Notifications\ChannelInterface;
use App\Services\Notifications\ChannelManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ChannelManager::class, function ($app) {
            $manager = new ChannelManager;
            $config  = config('notifications.channels', []);

            foreach ($config as $class) {
                $channel = $app->make($class);
                if ($channel instanceof ChannelInterface) {
                    $manager->register($channel);
                }
            }

            return $manager;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
