<?php

use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class);

it('keeps the default Sanctum stateful host list parseable', function () {
    $statefulDomains = config('sanctum.stateful');
    $applicationHost = parse_url(config('app.url'), PHP_URL_HOST);

    expect($statefulDomains)->toContain('::1');
    if (is_string($applicationHost) && $applicationHost !== '') {
        expect($statefulDomains)->toContain($applicationHost);
    }

    expect($statefulDomains)->not->toContain(','.$applicationHost);
});
