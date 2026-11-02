<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('authenticates requests with the bearer token', function (bool $verify) {
    config(['statamic.flysystem-pixxio.verify_ssl_certificate' => $verify]);
    Http::fake();

    Http::pixxio()->get('/files');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://pixxio.test/api/v1/files'
        && $request->hasHeader('Authorization', 'Bearer test-token'));
})->with([
    'ssl verification on' => true,
    'ssl verification off' => false,
]);

it('verifies ssl certificates by default', function () {
    expect(config('statamic.flysystem-pixxio.verify_ssl_certificate'))->toBeTrue();
});
