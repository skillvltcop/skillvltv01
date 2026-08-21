<?php

declare(strict_types=1);

use App\Domain\Blueprint\Enums\LifecycleStatus;

it('defines the four blueprint lifecycle states', function () {
    expect(LifecycleStatus::cases())
        ->toHaveCount(4);

    expect(LifecycleStatus::DRAFT->value)->toBe('draft');
    expect(LifecycleStatus::ACTIVE->value)->toBe('active');
    expect(LifecycleStatus::DEPRECATED->value)->toBe('deprecated');
    expect(LifecycleStatus::SUNSET->value)->toBe('sunset');
});