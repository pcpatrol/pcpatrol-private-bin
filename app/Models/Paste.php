<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PasteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * An end-to-end encrypted paste.
 *
 * @property string $id
 * @property string $payload
 * @property string $format
 * @property bool $has_password
 * @property bool $burn_after_reading
 * @property string $delete_token_hash
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * The server only ever stores the ciphertext. The key needed to read it lives
 * in the fragment of the share URL and is never sent to the server.
 */
class Paste extends Model
{
    /** @use HasFactory<PasteFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'payload',
        'format',
        'has_password',
        'burn_after_reading',
        'expires_at',
    ];

    /**
     * The formats a paste may be rendered as.
     *
     * @var list<string>
     */
    public const FORMATS = ['plaintext', 'markdown', 'code'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_password' => 'boolean',
            'burn_after_reading' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Generate the random public identifier used in the share URL.
     */
    public static function generateId(): string
    {
        return Str::lower(Str::random(24));
    }

    /**
     * Generate the secret that lets the creator delete the paste again.
     */
    public static function generateDeleteToken(): string
    {
        return Str::random(40);
    }

    /**
     * Hash a delete token for storage, so a database leak cannot delete pastes.
     */
    public static function hashDeleteToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Determine whether the given delete token belongs to this paste.
     */
    public function deleteTokenMatches(string $token): bool
    {
        return hash_equals($this->delete_token_hash, self::hashDeleteToken($token));
    }

    /**
     * Determine whether the paste has passed its expiry date.
     */
    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Limit the query to pastes that have not expired yet.
     *
     * @param  Builder<Paste>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Limit the query to pastes whose expiry date has passed.
     *
     * @param  Builder<Paste>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }
}
