<?php

use App\Models\Paste;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, mixed>
 */
function pastePayload(array $overrides = []): array
{
    return array_merge([
        'payload' => json_encode([
            'v' => 1,
            'ct' => base64_encode('ciphertext'),
            'iv' => base64_encode('123456789012'),
            'salt' => base64_encode('1234567890123456'),
            'iter' => 310000,
        ]),
        'format' => 'plaintext',
        'has_password' => false,
        'burn_after_reading' => false,
        'expires_in' => '1week',
    ], $overrides);
}

test('the paste form is shown on the home page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('paste/Create')
            ->where('defaultExpiry', config('paste.default_expiry'))
            ->has('expiryOptions'),
        );
});

test('an encrypted paste is stored and its links are returned', function () {
    $response = $this->postJson(route('paste.store'), pastePayload());

    $response->assertCreated()
        ->assertJsonStructure(['id', 'url', 'deleteUrl', 'expiresAt']);

    $paste = Paste::query()->firstOrFail();

    expect($paste->payload)->toBe(pastePayload()['payload'])
        ->and($paste->expires_at)->not->toBeNull()
        ->and($response->json('url'))->toBe(route('paste.show', $paste->id));
});

test('a paste kept forever has no expiry date', function () {
    $this->postJson(route('paste.store'), pastePayload(['expires_in' => 'never']))
        ->assertCreated();

    expect(Paste::query()->firstOrFail()->expires_at)->toBeNull();
});

test('the delete link returned on creation opens the confirmation screen', function () {
    $deleteUrl = $this->postJson(route('paste.store'), pastePayload())->json('deleteUrl');

    $this->get($deleteUrl)->assertOk();
});

test('an unknown retention period is rejected', function () {
    $this->postJson(route('paste.store'), pastePayload(['expires_in' => 'forever']))
        ->assertJsonValidationErrorFor('expires_in');
});

test('an unknown format is rejected', function () {
    $this->postJson(route('paste.store'), pastePayload(['format' => 'executable']))
        ->assertJsonValidationErrorFor('format');
});

test('a payload larger than the configured limit is rejected', function () {
    config(['paste.max_payload_bytes' => 64]);

    $this->postJson(route('paste.store'), pastePayload(['payload' => str_repeat('a', 65)]))
        ->assertJsonValidationErrorFor('payload');
});

test('reading a paste hands over the ciphertext', function () {
    $paste = Paste::factory()->create();

    $this->get(route('paste.show', $paste->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('paste/Show')
            ->where('paste.payload', $paste->payload)
            ->where('paste.burnAfterReading', false),
        );
});

test('a burn after reading paste withholds its ciphertext until it is confirmed', function () {
    $paste = Paste::factory()->burnAfterReading()->create();

    $this->get(route('paste.show', $paste->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('paste.payload', null)
            ->where('paste.burnAfterReading', true),
        );

    $this->assertDatabaseHas('pastes', ['id' => $paste->id]);
});

test('confirming a burn after reading paste returns the ciphertext and destroys it', function () {
    $paste = Paste::factory()->burnAfterReading()->create();

    $this->postJson(route('paste.reveal', $paste->id))
        ->assertOk()
        ->assertJson(['payload' => $paste->payload]);

    $this->assertDatabaseMissing('pastes', ['id' => $paste->id]);
});

test('a burned paste cannot be revealed a second time', function () {
    $paste = Paste::factory()->burnAfterReading()->create();

    $this->postJson(route('paste.reveal', $paste->id))->assertOk();
    $this->postJson(route('paste.reveal', $paste->id))->assertNotFound();
});

test('a paste that does not burn cannot be revealed', function () {
    $paste = Paste::factory()->create();

    $this->postJson(route('paste.reveal', $paste->id))->assertNotFound();

    $this->assertDatabaseHas('pastes', ['id' => $paste->id]);
});

test('an expired paste is no longer readable', function () {
    $paste = Paste::factory()->expired()->create();

    $this->get(route('paste.show', $paste->id))->assertNotFound();
});

test('an unknown paste is not found', function () {
    $this->get(route('paste.show', Paste::generateId()))->assertNotFound();
});

test('the delete screen is only shown for a matching token', function () {
    $paste = Paste::factory()->withDeleteToken('therealtoken1234567890')->create();

    $this->get(route('paste.delete', ['paste' => $paste->id, 'token' => 'therealtoken1234567890']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('paste/Delete'));

    $this->get(route('paste.delete', ['paste' => $paste->id, 'token' => 'awrongtoken1234567890']))
        ->assertNotFound();
});

test('a paste is deleted with its delete token', function () {
    $paste = Paste::factory()->withDeleteToken('therealtoken1234567890')->create();

    $this->delete(route('paste.destroy', $paste->id), ['token' => 'therealtoken1234567890'])
        ->assertRedirect(route('home'));

    $this->assertDatabaseMissing('pastes', ['id' => $paste->id]);
});

test('a paste is not deleted without the right token', function () {
    $paste = Paste::factory()->withDeleteToken('therealtoken1234567890')->create();

    $this->delete(route('paste.destroy', $paste->id), ['token' => 'awrongtoken1234567890'])
        ->assertNotFound();

    $this->assertDatabaseHas('pastes', ['id' => $paste->id]);
});

test('pruning removes expired pastes and keeps the rest', function () {
    $expired = Paste::factory()->expired()->create();
    $live = Paste::factory()->create();
    $permanent = Paste::factory()->neverExpires()->create();

    $this->artisan('pastes:prune')->assertSuccessful();

    $this->assertDatabaseMissing('pastes', ['id' => $expired->id]);
    $this->assertDatabaseHas('pastes', ['id' => $live->id]);
    $this->assertDatabaseHas('pastes', ['id' => $permanent->id]);
});
