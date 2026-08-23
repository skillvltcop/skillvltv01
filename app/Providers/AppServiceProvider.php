<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentBlueprintRepository;
use App\Application\Execution\Engine\ExecutionEngine;
use App\Application\Execution\Engine\ExecutionEngineContract;
use App\Application\Execution\Runtime\BehaviorRunner;
use App\Application\Execution\Runtime\Contracts\BehaviorRunner as BehaviorRunnerContract;
use App\Domain\Execution\Repositories\ExecutionRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentExecutionRepository;

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
        $this->app->bind(
            ExecutionRepository::class,
            EloquentExecutionRepository::class,
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
