<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class HomepageController extends AbstractController
{
    #[Route('/', name: 'homepage')]
    public function index(): RedirectResponse
    {
        return $this->redirect($this->generateUrl('app_localized', ['_locale' => 'en']));
    }

    #[Route('/{_locale}/', name: 'app_localized', requirements: ['_locale' => 'en|es'])]
    public function localized(Request $request): Response
    {
        return $this->render('base.html.twig', [
            'locale' => $request->getLocale(),
        ]);
    }
}
