<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class VersionCheck
{
    public const string RELEASES_ENDPOINT = 'https://api.github.com/repos/investbrainapp/investbrain/releases';

    public const string RELEASES_URL = 'https://github.com/investbrainapp/investbrain/releases';

    public const string CACHE_KEY = 'version-check:releases';

    /**
     * The version this instance is running, without a leading "v" (null when it isn't known).
     */
    public function current(): ?string
    {
        $version = trim((string) config('investbrain.version'));

        return $version === '' ? null : $this->normalize($version);
    }

    /**
     * Version status is only for admins of self-hosted instances that know their version.
     */
    public function visibleTo(?User $user): bool
    {
        return config('investbrain.self_hosted')
            && $user !== null
            && $user->can('admin')
            && $this->current() !== null;
    }

    /**
     * The newest published release, or null when it isn't known.
     */
    public function latest(): ?string
    {
        $releases = $this->releases();

        if ($releases === []) {
            return null;
        }

        usort($releases, version_compare(...));

        return end($releases);
    }

    /**
     * How many published releases are newer than the one this instance is running.
     */
    public function versionsBehind(): int
    {
        $current = (string) $this->current();

        return count(array_filter(
            $this->releases(),
            fn (string $release): bool => version_compare($release, $current, '>'),
        ));
    }

    public function updateAvailable(): bool
    {
        return $this->versionsBehind() > 0;
    }

    /**
     * Published release versions, cached so pages don't keep waiting on GitHub.
     *
     * @return list<string>
     */
    protected function releases(): array
    {
        if (! $this->isRelease($this->current())) {
            return [];
        }

        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        $releases = $this->fetchReleases();

        // a failed lookup is remembered too (as an empty list), just not for as long
        Cache::put(self::CACHE_KEY, $releases ?? [], $releases === null ? now()->addHour() : now()->addHours(12));

        return $releases ?? [];
    }

    /**
     * @return list<string>|null null when GitHub couldn't be reached
     */
    protected function fetchReleases(): ?array
    {
        try {
            $releases = Http::acceptJson()
                ->connectTimeout(2)
                ->timeout(3)
                ->get(self::RELEASES_ENDPOINT, ['per_page' => 100])
                ->throw()
                ->collect();
        } catch (Throwable) {
            return null;
        }

        return $releases
            ->reject(fn (mixed $release): bool => ! is_array($release) || ($release['prerelease'] ?? false) || ($release['draft'] ?? false))
            ->map(fn (array $release): string => $this->normalize((string) ($release['tag_name'] ?? '')))
            ->filter(fn (string $version): bool => $this->isRelease($version))
            ->unique()
            ->values()
            ->all();
    }

    protected function normalize(string $version): string
    {
        return preg_replace('/^v(?=\d)/i', '', trim($version));
    }

    /**
     * Only numbered releases (1.4.3) can be compared; builds such as "dev" can't.
     */
    protected function isRelease(?string $version): bool
    {
        return $version !== null && preg_match('/^\d+\.\d+\.\d+/', $version) === 1;
    }
}
