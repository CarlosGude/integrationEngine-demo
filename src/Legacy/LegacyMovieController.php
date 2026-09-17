<?php

declare(strict_types=1);

namespace App\Legacy;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LegacyMovieController extends AbstractController
{
    #[Route('/legacy/movie/{id}', name: 'legacy_movie')]
    public function show(int $id, TmdbApiService $tmdbApi): Response
    {
        $movie = $tmdbApi->getMovie($id);

        return $this->json($movie);
    }
}
