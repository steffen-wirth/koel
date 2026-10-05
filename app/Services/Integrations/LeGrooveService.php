<?php

namespace App\Services\Integrations;

use App\Models\Album;
use App\Services\GenreTagFilter;
use Illuminate\Support\Facades\Http;
use Normalizer;
use Throwable;

/**
 * Reads genres from the tags of the le-groove.de blog (WordPress), which has one post per track
 * titled "Artist – Title".
 */
class LeGrooveService
{
    private const API_URL = 'https://le-groove.de/wp-json/wp/v2';

    private const MAX_TITLE_QUERIES = 5;

    private const MAX_GENRES = 5;

    /** @var array<int, array{name: string, count: int}> */
    private array $tags = [];

    /** @return list<string> */
    public function getAlbumGenres(Album $album): array
    {
        if ($album->is_unknown || $album->artist->is_unknown) {
            return [];
        }

        try {
            return $this->resolveGenres($album);
        } catch (Throwable) {
            return [];
        }
    }

    /** @return list<string> */
    private function resolveGenres(Album $album): array
    {
        $titles = $album->songs()->pluck('title')->unique()->take(self::MAX_TITLE_QUERIES)->all();
        $songTitles = array_filter(array_map(self::normalize(...), $titles));
        $artist = self::normalize(preg_split('/,|&|\bfeat\b\.?|\bft\b\.?| x | and /i', $album->artist_name)[0]);

        if (!$songTitles || !$artist) {
            return [];
        }

        $tagIds = [];

        foreach ([$album->artist_name, ...$titles] as $query) {
            foreach ($this->search($query) as $post) {
                $title = self::normalize(html_entity_decode($post['title']['rendered'] ?? ''));

                if (!str_contains($title, $artist) || !$this->mentionsAny($title, $songTitles)) {
                    continue;
                }

                array_push($tagIds, ...array_unique($post['tags'] ?? []));
            }
        }

        return $this->namesOf(array_count_values($tagIds));
    }

    /** @param list<string> $needles */
    private function mentionsAny(string $haystack, array $needles): bool
    {
        return array_any($needles, static fn (string $needle) => str_contains($haystack, $needle));
    }

    /** @return list<array<string, mixed>> */
    private function search(string $query): array
    {
        $response = Http::timeout(15)->get(self::API_URL . '/posts', [
            'search' => $query,
            'per_page' => 50,
            '_fields' => 'title,tags',
        ]);

        return $response->successful() ? $response->json() : [];
    }

    /**
     * @param array<int, int> $uses Number of matched posts carrying each tag, keyed by tag ID
     *
     * @return list<string>
     */
    private function namesOf(array $uses): array
    {
        $missing = array_values(array_diff(array_keys($uses), array_keys($this->tags)));

        if ($missing) {
            $tags = Http::timeout(15)->get(self::API_URL . '/tags', [
                'include' => implode(',', $missing),
                'per_page' => 100,
            ])->json() ?? [];

            foreach ($tags as $tag) {
                $this->tags[$tag['id']] = ['name' => html_entity_decode($tag['name']), 'count' => $tag['count']];
            }
        }

        // Tags shared by more of the album's posts come first, then the ones used more across the blog.
        $names = collect($uses)
            ->map(fn (int $uses, int $id) => [
                'name' => $this->tags[$id]['name'] ?? '',
                'uses' => $uses,
                'count' => $this->tags[$id]['count'] ?? 0,
            ])
            ->sortBy([['uses', 'desc'], ['count', 'desc']])
            ->pluck('name');

        return array_slice(GenreTagFilter::filter($names), 0, self::MAX_GENRES);
    }

    private static function normalize(string $text): string
    {
        $text = class_exists(Normalizer::class) ? (Normalizer::normalize($text, Normalizer::NFKC) ?: $text) : $text;

        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($text)) ?? '';
    }
}
