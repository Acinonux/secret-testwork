<?php

use App\Models\Secret;

it('sends security headers on pages and api', function (string $method, Closure $url) {
    $response = $this->call($method, $url(), [], [], [], ['HTTP_ACCEPT' => 'application/json']);

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain("default-src 'self'")
        ->toContain("script-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->not->toContain('unsafe-eval')
        ->and(preg_match("/script-src[^;]*'unsafe-inline'/", $csp))->toBe(0);

    $response->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
})->with([
    'create page' => ['GET', fn () => '/'],
    'show page' => ['GET', fn () => '/s/'.str_repeat('a', 32)],
    'reveal api' => ['POST', fn () => '/api/secrets/'.Secret::create(['payload' => base64_encode(random_bytes(64)), 'size' => 64, 'expires_at' => now()->addHour()])->id.'/reveal'],
]);

it('sends HSTS only over https', function () {
    $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
    $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});
