<?php

use App\Models\Secret;
use Illuminate\Console\Scheduling\Schedule;

function makeSecret(int $ttlSeconds): Secret
{
    return Secret::create(['payload' => base64_encode(random_bytes(32)), 'size' => 32, 'expires_at' => now()->addSeconds($ttlSeconds)]);
}

it('deletes only expired secrets', function () {
    $short = makeSecret(300);
    $long = makeSecret(3600);

    $this->travel(10)->minutes();

    $this->artisan('secrets:purge')->expectsOutput('Удалено секретов: 1')->assertSuccessful();

    expect(Secret::find($short->id))->toBeNull()
        ->and(Secret::find($long->id))->not->toBeNull();
});

it('deletes in chunks until nothing is left', function () {
    foreach (range(1, 5) as $_) {
        makeSecret(60);
    }
    $this->travel(2)->minutes();

    $this->artisan('secrets:purge', ['--chunk' => 2])->expectsOutput('Удалено секретов: 5');

    expect(Secret::count())->toBe(0);
});

it('is scheduled every minute', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command, 'secrets:purge'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *');
});
