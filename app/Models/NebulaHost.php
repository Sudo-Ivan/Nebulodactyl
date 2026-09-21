<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $node_id
 * @property string $name
 * @property string $ip
 * @property string $public_key
 * @property string $fingerprint
 * @property string $certificate
 * @property string|null $serial
 * @property array|null $groups
 * @property \Carbon\CarbonImmutable|null $expires_at
 * @property \Carbon\CarbonImmutable|null $revoked_at
 * @property \Carbon\CarbonImmutable|null $last_seen_at
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property \Pterodactyl\Models\Node|null $node
 */
class NebulaHost extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'nebula_hosts';

    /**
     * Fields that are mass assignable.
     */
    protected $fillable = [
        'node_id', 'name', 'ip', 'public_key', 'fingerprint',
        'certificate', 'serial', 'groups', 'expires_at',
        'revoked_at', 'last_seen_at',
    ];

    /**
     * Cast values to their correct type.
     */
    protected function casts(): array
    {
        return [
            'node_id' => 'integer',
            'groups' => 'array',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
        ];
    }

    /**
     * The panel node this certificate belongs to.
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    /**
     * Whether this certificate is currently usable on the overlay.
     */
    public function isActive(): bool
    {
        if (!is_null($this->revoked_at)) {
            return false;
        }

        return is_null($this->expires_at) || $this->expires_at->isFuture();
    }
}
