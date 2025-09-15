<?php

namespace NiekPH\LaravelVisitorTracking\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NiekPH\LaravelVisitorTracking\Database\Factories\EventFactory;
use NiekPH\LaravelVisitorTracking\VisitorTracking;

class Event extends Model
{
    use HasFactory;

    public $timestamps = false;

    public function getTable(): string
    {
        return config('visitor_tracking.table_prefix').'events';
    }

    public function getConnectionName()
    {
        return config('visitor-tracking.db_connection_name');
    }

    protected static function newFactory(): EventFactory
    {
        return EventFactory::new();
    }

    protected $fillable = [
        'visitor_id',
        'name',
        'url',
        'data',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(VisitorTracking::$visitorModel);
    }
}
