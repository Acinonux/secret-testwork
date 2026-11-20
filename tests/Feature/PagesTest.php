<?php

use App\Models\Secret;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the create page with options', function () {
    $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Create')
        ->where('ttlOptions', config('secrets.ttl_options'))
        ->where('defaultTtl', config('secrets.default_ttl'))
        ->where('maxFileBytes', config('secrets.max_file_bytes')));
});

it('renders the show page without consuming the secret', function () {
    $secret = Secret::create(['payload' => random_bytes(64), 'size' => 64, 'expires_at' => now()->addHour()]);

    $this->get("/s/{$secret->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Show')
        ->where('id', $secret->id)
        ->missing('payload'));

    expect(Secret::find($secret->id))->not->toBeNull();
});

it('returns 404 for malformed show links', function () {
    $this->get('/s/not-a-valid-id')->assertNotFound();
});
