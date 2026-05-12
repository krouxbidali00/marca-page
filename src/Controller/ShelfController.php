<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ShelfController extends AbstractController
{
    #[Route('/shelves', name: 'app_shelf_create', methods: ['POST'])]
    public function create(): Response
    {
        return $this->redirectToRoute('app_library');
    }
}
