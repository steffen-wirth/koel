<?php

namespace App\Services\Integrations;

use App\Models\Album;
use App\Services\GenreTagFilter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Normalizer;
use Throwable;

/**
 * Finds an album's Bandcamp page through the Google Custom Search API (Bandcamp's own search sits behind a bot
 * challenge) and reads the tags the artist gave it. Bandcamp is queried slowly, and no further requests
 * are made once it starts refusing them.
 */
class BandcampService
{
    private const MIN_SECONDS_BETWEEN_PAGE_REQUESTS = 4;

    private const MAX_GENRES = 5;

    /** The free quota of the Google Custom Search API. */
    private const MAX_SEARCHES_PER_DAY = 100;

    private static float $lastPageRequestAt = 0.0;

    private static bool $blocked = false;

    public static function enabled(): bool
    {
        return config('koel.services.google_search.key') && config('koel.services.google_search.engine_id');
    }

    /** @return list<string> */
    public function getAlbumGenres(Album $album): array
    {
        if (!static::enabled() || self::$blocked || $album->is_unknown || $album->artist->is_unknown) {
            return [];
        }

        try {
            $url = $this->findPageUrl($album);

            return $url ? $this->readTags($url) : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function findPageUrl(Album $album): ?string
    {
        if (!self::reserveSearch()) {
            return null;
        }

        $response = Http::timeout(15)->get(config('koel.services.google_search.endpoint'), [
            'key' => config('koel.services.google_search.key'),
            'cx' => config('koel.services.google_search.engine_id'),
            'q' => sprintf('"%s" "%s" site:bandcamp.com', $album->artist_name, $album->name),
            'num' => 10,
        ]);

        if (!$response->successful()) {
            // quota exhausted, missing access, bad key... retrying for every album would only burn requests
            self::$blocked = true;
            Log::warning('Google search failed, skipping Bandcamp lookups', ['response' => $response->body()]);

            return null;
        }

        $name = self::normalize($album->name);

        foreach ($response->json('items', []) as $result) {
            $isReleasePage = preg_match('~^https://[a-z0-9-]+\.bandcamp\.com/album/~i', $result['link'] ?? '');

            if ($isReleasePage && str_contains(self::normalize($result['title'] ?? ''), $name)) {
                return $result['link'];
            }
        }

        return null;
    }

    /**
     * Count a search against today's quota, which Google resets at midnight Pacific time.
     */
    private static function reserveSearch(): bool
    {
        $key = 'google-search-count:' . Carbon::now('America/Los_Angeles')->toDateString();

        Cache::add($key, 0, now()->addDay());

        return Cache::increment($key) <= self::MAX_SEARCHES_PER_DAY;
    }

    /** @return list<string> */
    private function readTags(string $url): array
    {
        $wait = self::MIN_SECONDS_BETWEEN_PAGE_REQUESTS - (microtime(true) - self::$lastPageRequestAt);

        if ($wait > 0) {
            usleep((int) ($wait * 1_000_000));
        }

        self::$lastPageRequestAt = microtime(true);

        $response = Http::timeout(15)->withUserAgent(koel_user_agent())->get($url);

        $html = $response->body();

        if (!$response->successful() || str_contains($html, 'Client Challenge')) {
            self::$blocked = true;

            return [];
        }

        if (!preg_match('/"keywords"\s*:\s*(\[[^\]]*\])/', $html, $matches)) {
            return [];
        }

        return array_slice(
            GenreTagFilter::filter(json_decode($matches[1], true) ?: [], dropCapitalized: false),
            0,
            self::MAX_GENRES,
        );
    }

    private static function normalize(string $text): string
    {
        $text = class_exists(Normalizer::class) ? (Normalizer::normalize($text, Normalizer::NFKC) ?: $text) : $text;

        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($text)) ?? '';
    }
}
