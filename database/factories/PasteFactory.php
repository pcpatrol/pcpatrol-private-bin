<?php

namespace Database\Factories;

use App\Models\Paste;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Paste>
 */
class PasteFactory extends Factory
{
    protected $model = Paste::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => Paste::generateId(),
            'payload' => base64_encode($this->faker->text(200)),
            'format' => 'plaintext',
            'has_password' => false,
            'burn_after_reading' => false,
            'delete_token_hash' => Paste::hashDeleteToken(Paste::generateDeleteToken()),
            'expires_at' => now()->addWeek(),
        ];
    }

    /**
     * Indicate that the paste should be destroyed once it has been read.
     */
    public function burnAfterReading(): static
    {
        return $this->state(fn (array $attributes): array => [
            'burn_after_reading' => true,
        ]);
    }

    /**
     * Indicate that the paste is kept until it is deleted by hand.
     */
    public function neverExpires(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => null,
        ]);
    }

    /**
     * Indicate that the paste's retention period has already passed.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    /**
     * Indicate that the paste needs an extra password to decrypt.
     */
    public function passwordProtected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'has_password' => true,
        ]);
    }

    /**
     * Use a known delete token so a test can exercise the delete link.
     */
    public function withDeleteToken(string $token): static
    {
        return $this->state(fn (array $attributes): array => [
            'delete_token_hash' => Paste::hashDeleteToken($token),
        ]);
    }
}
