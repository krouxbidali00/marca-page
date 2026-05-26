<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_library');
        }

        $response = $this->render('home/index.html.twig');
        $response->setPublic();
        $response->setSharedMaxAge(3600);
        $response->setMaxAge(600);
        // Prevent AbstractSessionListener from overriding these headers when the
        // session is touched (e.g. by the remember-me listener on anonymous requests).
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, '1');

        return $response;
    }
}
