<?php

namespace NiekPH\LaravelVisitorTracking\Models;

use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use NiekPH\LaravelVisitorTracking\Database\Factories\VisitorFactory;
use NiekPH\LaravelVisitorTracking\VisitorTag;
use NiekPH\LaravelVisitorTracking\VisitorTracking;

class Visitor extends Model
{
    use HasFactory;

    public function getTable(): string
    {
        return config('visitor-tracking.table_prefix').'visitors';
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
     * @return self The created or retrieved instance populated with data extracted from the request.
     */
    public function fromRequest(Request $request): self
    {
        $tag = new VisitorTag()->retrieve($request);
        $clientHints = config('visitor-tracking.enable_client_hints') ? ClientHints::factory($_SERVER) : null;

        $user = $request->user();
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();

        $deviceDetector = new DeviceDetector($userAgent, $clientHints);
        $deviceDetector->parse();

        return static::firstOrCreate([
            'tag' => $tag,
            'user_id' => $user?->id,
            'user_agent' => $userAgent,
            'ip_address' => $ipAddress,
            'is_bot' => $deviceDetector->isBot(),
            'device' => $deviceDetector->getDeviceName(),
            'browser' => $deviceDetector->getClient('name'),
            'platform' => $deviceDetector->getOs('name'),
            'platform_version' => $deviceDetector->getOs('version'),
        ]
        );
    }
}
