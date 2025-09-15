<?php

namespace NiekPH\LaravelVisitorTracking\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User;
use NiekPH\LaravelVisitorTracking\Database\Factories\VisitorFactory;
use NiekPH\LaravelVisitorTracking\VisitorTracking;

class Visitor extends Model
{
    use HasFactory;

    public function getTable(): string
    {
        return config('visitor_tracking.table_prefix').'visitors';
    }

    public function getConnectionName()
    {
        return config('visitor-tracking.db_connection_name');
    }

    protected static function newFactory(): VisitorFactory
    {
        return VisitorFactory::new();
    }

    protected $fillable = [
        'tag',
        'ip_address',
        'user_agent',
        'user_id',
        'is_bot',
        'device',
        'browser',
        'platform',
        'platform_version',
        'route_name',
        'data',
        'created_at',
        'url',
        'name',
    ];

    protected function casts(): array
    {
        return [
            'is_bot' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(VisitorTracking::$eventModel);
    }
}
