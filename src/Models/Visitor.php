<?php

namespace NiekPH\LaravelVisitorTracking\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use NiekPH\LaravelVisitorTracking\ClientData;
use NiekPH\LaravelVisitorTracking\Database\Factories\VisitorFactory;
use NiekPH\LaravelVisitorTracking\VisitorTag;
use NiekPH\LaravelVisitorTracking\VisitorTracking;

class Visitor extends Model
{
    use HasFactory;

    public function getTable(): string
    {
        return config('visitor-tracking.database.tables.visitors', 'visitors');
    }

    public function getConnectionName()
    {
        return config('visitor-tracking.database.connection');
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
        'geo_country',
        'geo_region',
        'geo_city',
        'geo_latitude',
        'geo_longitude',
    ];

    protected function casts(): array
    {
        return [
            'is_bot' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        $userModel = config('auth.providers.users.model') ?? User::class;
        $userColumn = config('visitor-tracking.users.column', 'id');

        return $this->belongsTo($userModel, 'user_id', $userColumn);
    }

    public function events(): HasMany
    {
        return $this->hasMany(VisitorTracking::$eventModel);
    }

    /**
     * Creates or retrieves an instance based on the provided request.
     *
     * @param  Request  $request  The HTTP request instance containing the incoming client data.
     * @return static The created or retrieved instance populated with data extracted from the request.
     */
    public static function fromRequest(Request $request): static
    {
        $userId = $request->user()?->id;

        $tag = new VisitorTag($request)->getTag();
        $clientData = new ClientData($request)->detect();

        return static::query()->firstOrCreate(
            ['tag' => $tag],
            [
                'tag' => $tag,
                'user_id' => $userId,
                'user_agent' => $clientData->getUserAgent(),
                'ip_address' => $clientData->getIpAddress(),
                'is_bot' => $clientData->isBot(),
                'device' => $clientData->getDevice(),
                'browser' => $clientData->getBrowser(),
                'platform' => $clientData->getPlatform(),
                'platform_version' => $clientData->getPlatformVersion(),
                'geo_country' => $clientData->getCountryCode(),
                'geo_region' => $clientData->getRegion(),
                'geo_city' => $clientData->getCity(),
                'geo_latitude' => $clientData->getLatitude(),
                'geo_longitude' => $clientData->getLongitude(),
            ]
        );
    }
}
