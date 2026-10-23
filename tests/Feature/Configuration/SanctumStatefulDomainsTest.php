<?php

use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class);

it('keeps default Sanctum stateful hosts as separate entries', function () {
    $statefulDomains = config('sanctum.stateful');

    expect($statefulDomains)->toContain('::1');
    expect($statefulDomains)->toContain(Sanctum::currentApplicationUrlWithPort());
});
