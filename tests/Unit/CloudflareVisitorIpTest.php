<?php

use App\Http\Middleware\UseCloudflareVisitorIp;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    Request::setTrustedProxies(['172.16.0.0/12', '173.245.48.0/20'], Request::HEADER_X_FORWARDED_FOR);
});

afterEach(function () {
    Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
});

test('uses the Cloudflare visitor after Anubis rewrites the forwarding chain', function (string $visitor) {
    $request = Request::create('https://mtg.betz.coffee/', server: [
        'REMOTE_ADDR' => '172.18.0.5',
        'HTTP_X_FORWARDED_FOR' => '173.245.48.1',
        'HTTP_CF_CONNECTING_IP' => $visitor,
    ]);
    $response = (new UseCloudflareVisitorIp)->handle($request, fn (Request $request) => new Response($request->ip()));

    expect($response->getContent())->toBe($visitor);
})->with(['139.15.2.3', '2a03:cc00::1234', '173.245.48.2']);

test('does not accept a malformed Cloudflare forwarding list', function () {
    $request = Request::create('http://localhost/', server: [
        'REMOTE_ADDR' => '172.18.0.5',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.7',
        'HTTP_CF_CONNECTING_IP' => '139.15.2.3, 198.51.100.2',
    ]);
    $response = (new UseCloudflareVisitorIp)->handle($request, fn (Request $request) => new Response($request->ip()));

    expect($response->getContent())->toBe('203.0.113.7');
});

test('retains normal local requests when Cloudflare headers are absent', function () {
    $request = Request::create('http://localhost/', server: ['REMOTE_ADDR' => '127.0.0.1']);
    $response = (new UseCloudflareVisitorIp)->handle($request, fn (Request $request) => new Response($request->ip()));

    expect($response->getContent())->toBe('127.0.0.1');
});
