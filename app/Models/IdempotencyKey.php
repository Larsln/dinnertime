<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * Answer to a request a client marked with a key, kept so that a repeat
 * of the request is answered without running it again.
 *
 * @property string $key
 * @property int $status
 * @property string $body
 */
#[Fillable(['key', 'status', 'body', 'created_at'])]
class IdempotencyKey extends Model
{
    use Prunable;

    private const int DAYS_KEPT = 30;

    public $timestamps = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Keys no client repeats any more.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(self::DAYS_KEPT));
    }
}
