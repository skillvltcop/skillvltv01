<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use App\Application\Execution\Engine\ExecutionEngine;
use App\Application\Execution\Engine\ExecutionEngineContract;
use App\Application\Execution\Runtime\BehaviorRunner;
use App\Application\Execution\Runtime\Contracts\BehaviorRunner as BehaviorRunnerContract;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            BlueprintRepository::class,
            EloquentBlueprintRepository::class,
        );
        $this->app->bind(
            BehaviorRunnerContract::class,
            BehaviorRunner::class,
        );
        $this->app->bind(
            ExecutionEngineContract::class,
            ExecutionEngine::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
