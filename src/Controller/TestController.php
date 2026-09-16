<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class TestController
{
    #[Route('/test-plain', name: 'test_plain')]
    public function plain(): Response
    {
        $response = new Response('TEST');
        $response->headers->set('Content-Type', 'text/plain');
        return $response;
    }
}
