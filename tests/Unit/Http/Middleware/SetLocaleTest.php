<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
);

it('defaults to arabic', function () {
    $request = Request::create('/');
    app()->setLocale('ar');

    $response = app(Pipeline::class)
        ->send($request)
        ->through([SetLocale::class])
        ->then(fn ($request) => response()->json([
            'locale' => app()->getLocale(),
        ]));

    expect($response->getData(true)['locale'])->toBe('ar');
});

it('uses a supported locale from the session', function () {
    $request = Request::create('/');
    $request->setLaravelSession(
        app('session')->driver(),
    );
    $request->session()->put('locale', 'fr');

    $response = app(Pipeline::class)
        ->send($request)
        ->through([SetLocale::class])
        ->then(fn ($request) => response()->json([
            'locale' => app()->getLocale(),
        ]));

    expect($response->getData(true)['locale'])->toBe('fr');
});

it('falls back to arabic for an unsupported locale', function () {
    $request = Request::create('/');
    $request->setLaravelSession(
        app('session')->driver(),
    );
    $request->session()->put('locale', 'de');

    $response = app(Pipeline::class)
        ->send($request)
        ->through([SetLocale::class])
        ->then(fn ($request) => response()->json([
            'locale' => app()->getLocale(),
        ]));

    expect($response->getData(true)['locale'])->toBe('ar');
});
