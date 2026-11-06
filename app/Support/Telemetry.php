<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Throwable;

/**
 * Anonymous usage statistics for self-hosted instances.
 *
 * Only the version, the environment, which features are turned on and aggregate counts are sent.
 * Never names, emails, symbols, amounts, addresses or the instance's domain.
 */
class Telemetry
{
    public const SCHEMA = 1;

    public static function enabled(): bool
    {
        return (bool) config('investbrain.self_hosted') && (bool) config('investbrain.telemetry.enabled');
    }

    /**
     * A random ID for this instance, kept in the storage volume so restarts report as the same instance.
     */
    public static function instanceId(): string
    {
        $path = (string) config('investbrain.telemetry.instance_id_path');

        $id = File::exists($path) ? trim((string) File::get($path)) : '';

        if (! Str::isUuid($id)) {
            $id = (string) Str::uuid();

            File::ensureDirectoryExists(dirname($path));
            File::put($path, $id);
        }

        return $id;
    }

    /**
     * @return array{schema: int, event: string, instance_id: string, version: string, environment: array<string, mixed>, features: array<string, mixed>, stats: array<string, mixed>}
     */
    public static function payload(string $event = 'started'): array
    {
        return [
            'schema' => static::SCHEMA,
            'event' => $event,
            'instance_id' => static::instanceId(),
            'version' => (string) config('investbrain.version'),
            'environment' => [
                'php' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'.'.PHP_RELEASE_VERSION,
                'os' => PHP_OS_FAMILY,
                'arch' => php_uname('m'),
                'database' => DB::connection()->getDriverName(),
                'docker' => File::exists('/.dockerenv'),
                'timezone' => (string) config('app.timezone'),
            ],
            'features' => [
                'market_data_providers' => static::marketDataProviders(),
                'base_currency' => (string) config('investbrain.base_currency'),
                'registration' => Features::enabled(Features::registration()),
                'login_providers' => array_values(array_filter(array_map('trim', explode(',', (string) config('services.enabled_login_providers'))))),
                'ai_chat' => (bool) config('services.ai_chat_enabled'),
                'ai_provider' => config('services.ai_chat_enabled') ? (string) config('ai.default') : null,
                'mcp_server' => (bool) config('services.mcp_server_enabled'),
                'mail' => (string) config('mail.default'),
            ],
            'stats' => static::stats(),
        ];
    }

    /**
     * Send the payload. Never throws: a failed report must not stop the instance from starting.
     */
    public static function send(string $event = 'started'): bool
    {
        try {
            return Http::connectTimeout(3)
                ->timeout(5)
                ->acceptJson()
                ->post((string) config('investbrain.telemetry.url'), static::payload($event))
                ->successful();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Configured providers, with custom ones reported as "custom" so their names aren't sent.
     *
     * @return array<int, string>
     */
    protected static function marketDataProviders(): array
    {
        $builtIn = array_keys((array) config('investbrain.interfaces'));

        return collect(explode(',', (string) config('investbrain.provider')))
            ->map(fn (string $provider): string => trim($provider))
            ->filter()
            ->map(fn (string $provider): string => in_array($provider, $builtIn, true) ? $provider : 'custom')
            ->values()
            ->all();
    }

    /**
     * @return array{users: int, active_users_30d: int, two_factor_users: int, portfolios: int, transactions: int, open_holdings: int, symbols: int, locales: array<string, int>, display_currencies: array<string, int>}
     */
    protected static function stats(): array
    {
        $options = DB::table('users')
            ->pluck('options')
            ->map(fn ($options): array => is_string($options) ? (array) json_decode($options, true) : (array) $options);

        return [
            'users' => DB::table('users')->count(),
            'active_users_30d' => DB::table('users')->where('last_seen_at', '>=', now()->subDays(30))->count(),
            'two_factor_users' => DB::table('users')->whereNotNull('two_factor_confirmed_at')->count(),
            'portfolios' => DB::table('portfolios')->count(),
            'transactions' => DB::table('transactions')->count(),
            'open_holdings' => DB::table('holdings')->where('quantity', '>', 0)->count(),
            'symbols' => DB::table('market_data')->count(),
            'locales' => static::tally($options->map(fn (array $option): string => (string) ($option['locale'] ?? config('app.locale')))),
            'display_currencies' => static::tally($options->map(fn (array $option): string => strtoupper((string) ($option['display_currency'] ?? config('investbrain.base_currency'))))),
        ];
    }

    /**
     * Count each value, keeping the 20 most common so the payload stays small.
     *
     * @param  Collection<int, string>  $values
     * @return array<string, int>
     */
    protected static function tally($values): array
    {
        return $values
            ->map(fn (string $value): string => Str::limit($value, 10, ''))
            ->countBy()
            ->sortDesc()
            ->take(20)
            ->all();
    }
}
