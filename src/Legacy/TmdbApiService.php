<?php

declare(strict_types=1);

namespace App\Legacy;

use Symfony\Contracts\HttpClient\HttpClientInterface;

// tour:start problem/antipattern1-god-class
/**
 * Antipattern #1: God class with multiple responsibilities (HTTP, parsing, caching, mapping)
 * Antipattern #2: Tight coupling to TMDB API details
 * Antipattern #3: No reusability — everything baked into one service
 * Antipattern #4: Manual caching logic (no abstraction)
 * Antipattern #5: Error handling buried in business logic
 */
final class TmdbApiService
{
    private array $configCache = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $tmdbBaseUrl,
        private readonly string $tmdbToken,
    ) {
    }

    // tour:start problem/antipattern2-tight-coupling
    public function getMovie(int $movieId): array
    {
        $movieResponse = $this->httpClient->request('GET', "{$this->tmdbBaseUrl}/3/movie/{$movieId}", [
            'headers' => ['Authorization' => "Bearer {$this->tmdbToken}"],
        ]);

        $movieData = \json_decode($movieResponse->getContent(), associative: true);

        $configData = $this->getConfiguration();

        $posterUrl = $this->buildPosterUrl(
            $movieData['poster_path'] ?? '',
            $configData['images']['secure_base_url'] ?? '',
            $configData['images']['poster_sizes'] ?? [],
        );

        return [
            'id' => $movieData['id'],
            'title' => $movieData['title'],
            'overview' => $movieData['overview'],
            'poster_path' => $movieData['poster_path'],
            'vote_average' => $movieData['vote_average'],
            'release_date' => $movieData['release_date'],
            'poster_url' => $posterUrl,
        ];
    }
    // tour:end

    // tour:start problem/antipattern4-manual-caching
    private function getConfiguration(): array
    {
        if (!empty($this->configCache)) {
            return $this->configCache;
        }

        $response = $this->httpClient->request('GET', "{$this->tmdbBaseUrl}/3/configuration", [
            'headers' => ['Authorization' => "Bearer {$this->tmdbToken}"],
        ]);

        $this->configCache = \json_decode($response->getContent(), associative: true);

        return $this->configCache;
    }
    // tour:end

    private function buildPosterUrl(string $posterPath, string $secureBaseUrl, array $sizes): string
    {
        if (empty($posterPath)) {
            return '';
        }

        $preferredSize = 'w500';
        if (!\in_array($preferredSize, $sizes, true)) {
            $preferredSize = $sizes[0] ?? 'original';
        }

        return $secureBaseUrl.$preferredSize.$posterPath;
    }
}
// tour:end
