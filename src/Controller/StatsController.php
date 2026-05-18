<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\StatsPeriod;
use App\Service\StatsAggregator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class StatsController extends AbstractController
{
    #[Route('/stats', name: 'app_stats', methods: ['GET'])]
    public function index(Request $request, StatsAggregator $aggregator): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $period = StatsPeriod::fromRequest($request);
        $stats = $aggregator->compute($user, $period);

        return $this->render('stats/index.html.twig', [
            'stats' => $stats,
            'period' => $period,
            'periods' => StatsPeriod::cases(),
        ]);
    }
}
