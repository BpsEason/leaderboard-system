<?php

namespace App\Providers;

use App\Repositories\Contracts\PlayerScoreRepositoryInterface;
use App\Repositories\Eloquent\EloquentPlayerScoreRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(
            PlayerScoreRepositoryInterface::class,
            EloquentPlayerScoreRepository::class
        );
        // Bind other repositories here
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
