<?php

declare(strict_types=1);

namespace App\Controller;

use App\Billing\Application\RentalPaymentGateway;
use App\Catalog\Application\MovieCatalogGateway;
use IntegrationEngine\Core\Exception\RequestResponseException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;

final class StorefrontController extends AbstractController
{
    #[Route('/{_locale}/store', name: 'storefront', requirements: ['_locale' => 'en|es'])]
    public function index(MovieCatalogGateway $gateway, LoggerInterface $logger): Response
    {
        $featuredMovieIds = [
            299536, 550, 278, 496243, 680, 109, 129, 238, 240, 424,
            389, 505642, 338762, 346698, 16662, 11, 13, 278935, 299534, 808,
        ];

        try {
            $movies = $gateway->getMoviesByIdBatch($featuredMovieIds);
        } catch (RequestResponseException $exception) {
            $logger->error('Storefront catalog request failed.', ['status_code' => $exception->statusCode]);

            return $this->render('store/storefront.html.twig', [
                'movies' => [],
                'catalog_unavailable' => true,
            ], new Response(status: Response::HTTP_SERVICE_UNAVAILABLE));
        }

        return $this->render('store/storefront.html.twig', [
            'movies' => $movies,
        ]);
    }

    #[Route('/{_locale}/store/{movieId}/rent', name: 'storefront_rent', requirements: ['_locale' => 'en|es', 'movieId' => '[1-9][0-9]*'], methods: ['POST'])]
    public function rent(int $movieId, Request $request, RentalPaymentGateway $gateway, LoggerInterface $logger): Response
    {
        if (!$this->isCsrfTokenValid('rent_'.$movieId, $request->request->getString('_token'))) {
            throw new HttpException(Response::HTTP_FORBIDDEN, 'Invalid CSRF token.');
        }

        try {
            $payment = $gateway->rentMovie(movieId: $movieId, amountCents: 399, currency: 'usd');
        } catch (RequestResponseException $exception) {
            $logger->error('Rental payment request failed.', ['status_code' => $exception->statusCode]);

            return $this->render('store/rental.html.twig', [
                'payment' => null,
            ], new Response(status: Response::HTTP_SERVICE_UNAVAILABLE));
        }

        return $this->render('store/rental.html.twig', ['payment' => $payment]);
    }

}
