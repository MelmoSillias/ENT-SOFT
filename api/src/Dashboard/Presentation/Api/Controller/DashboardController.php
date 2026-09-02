<?php

namespace App\Dashboard\Presentation\Api\Controller;

use App\Dashboard\Application\Query\GetDashboardSummary\GetDashboardSummaryHandler;
use App\Dashboard\Application\Query\GetDashboardSummary\GetDashboardSummaryQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/dashboard')]
final class DashboardController extends AbstractController
{
    #[Route('/summary', name: 'api_dashboard_summary', methods: ['GET'])]
    #[IsGranted('dashboard.view')]
    public function summary(GetDashboardSummaryHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetDashboardSummaryQuery()));
    }
}
