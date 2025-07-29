<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return new Response(
            '<html><body><h1>Hello from Symfony on AWS Lambda!</h1><p>Your deployment is working!</p></body></html>'
        );
    }
} 