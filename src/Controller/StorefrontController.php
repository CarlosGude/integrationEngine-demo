<?php

declare(strict_types=1);

namespace App\Controller;

use App\Catalog\Application\MovieCatalogGateway;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StorefrontController extends AbstractController
{
    #[Route('/{_locale}/store', name: 'storefront', requirements: ['_locale' => 'en|es'])]
    public function index(MovieCatalogGateway $gateway): Response
    {
        $featuredMovieIds = [
            299536, 550, 278, 496243, 680, 109, 129, 238, 240, 424,
            389, 505642, 338762, 346698, 16662, 11, 13, 278935, 299534, 808,
        ];

        $movies = $gateway->getMoviesByIdBatch($featuredMovieIds);

        return $this->render('store/storefront.html.twig', [
            'movies' => $movies,
        ]);
    }
}
