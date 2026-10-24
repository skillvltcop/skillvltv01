<?php

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

uses(TestCase::class);

function csrfTestMiddleware(): ValidateCsrfToken
{
    return new class (app(), app(\Illuminate\Encryption\Encrypter::class)) extends ValidateCsrfToken {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    };
}

function csrfTestRequest(?string $token): Request
{
    $session = app('session')->driver();
    $session->start();

    $request = Request::create('/csrf-test', 'POST', $token === null ? [] : ['_token' => $token]);
    $request->setLaravelSession($session);

    return $request;
}

it('accepts a request with a matching session csrf token', function () {
    $session = app('session')->driver();
    $session->start();

    $request = Request::create('/csrf-test', 'POST', [
        '_token' => $session->token(),
    ]);
    $request->setLaravelSession($session);

    $response = csrfTestMiddleware()->handle(
        $request,
        fn () => new Response('ok'),
    );

    expect($response->getStatusCode())->toBe(200);
});

it('rejects a request with a missing or mismatched csrf token', function (?string $token) {
    $request = csrfTestRequest($token);

    expect(fn () => csrfTestMiddleware()->handle(
        $request,
        fn () => new Response('ok'),
    ))->toThrow(TokenMismatchException::class);
})->with([
    'missing token' => [null],
    'mismatched token' => ['invalid-csrf-token'],
]);
