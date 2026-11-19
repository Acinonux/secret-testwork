<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Secret extends Model
{
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['payload', 'size', 'expires_at'];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Secret $secret) {
            $secret->id ??= Str::random(32);
        });
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('expires_at', '>', now());
    }

    public function scopeExpired(Builder $query): void
    {
        $query->where('expires_at', '<=', now());
    }
}
