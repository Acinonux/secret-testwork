<?php

use App\Models\Secret;

function blob(int $bytes = 64): string
{
    return base64_encode(random_bytes($bytes));
}

it('stores an encrypted secret', function () {
    $payload = blob();

    $res = $this->postJson('/api/secrets', ['payload' => $payload, 'ttl' => 3600])
        ->assertCreated()
        ->assertJsonStructure(['id', 'expires_at']);

    $secret = Secret::findOrFail($res->json('id'));
    expect($secret->payload)->toBe($payload)
        ->and($secret->size)->toBe(64)
        ->and(strlen($secret->id))->toBe(32)
        ->and($secret->expires_at->diffInSeconds(now()->addHour(), true))->toBeLessThan(5);
});

it('validates input', function (array $data) {
    $this->postJson('/api/secrets', $data)->assertUnprocessable();
})->with([
    'no payload' => [['ttl' => 3600]],
    'no ttl' => [['payload' => base64_encode(str_repeat('a', 64))]],
    'bad ttl' => [['payload' => base64_encode(str_repeat('a', 64)), 'ttl' => 42]],
    'not base64' => [['payload' => '!!!not-base64!!!', 'ttl' => 3600]],
    'too small' => [['payload' => base64_encode('short'), 'ttl' => 3600]],
]);

it('rejects payloads over the size limit', function () {
    config(['secrets.max_bytes' => 1024]);

    $this->postJson('/api/secrets', ['payload' => blob(1025), 'ttl' => 3600])->assertUnprocessable();
    $this->postJson('/api/secrets', ['payload' => blob(1024), 'ttl' => 3600])->assertCreated();
});

it('reveals a secret only once', function () {
    $payload = blob();
    $id = $this->postJson('/api/secrets', ['payload' => $payload, 'ttl' => 300])->json('id');

    $this->postJson("/api/secrets/{$id}/reveal")->assertOk()->assertExactJson(['payload' => $payload]);
    expect(Secret::find($id))->toBeNull();

    $this->postJson("/api/secrets/{$id}/reveal")->assertNotFound();
});

it('does not reveal an expired secret even before purge', function () {
    $secret = Secret::create(['payload' => base64_encode(random_bytes(64)), 'size' => 64, 'expires_at' => now()->addMinutes(5)]);

    $this->travel(6)->minutes();

    $this->postJson("/api/secrets/{$secret->id}/reveal")->assertNotFound();
});

it('returns 404 for unknown and malformed ids', function () {
    $this->postJson('/api/secrets/'.str_repeat('a', 32).'/reveal')->assertNotFound();
    $this->postJson('/api/secrets/short/reveal')->assertNotFound();
});

it('does not expose payload when serialized', function () {
    $secret = Secret::create(['payload' => base64_encode(random_bytes(64)), 'size' => 64, 'expires_at' => now()->addHour()]);

    expect($secret->toArray())->not->toHaveKey('payload');
});

it('rate limits secret creation', function () {
    foreach (range(1, 30) as $_) {
        $this->postJson('/api/secrets', ['payload' => blob(), 'ttl' => 300])->assertCreated();
    }
    $this->postJson('/api/secrets', ['payload' => blob(), 'ttl' => 300])->assertTooManyRequests();
});
