<?php

namespace App\AccessAudit\Presentation\Api\Controller;

use App\AccessAudit\Domain\Repository\HistoriqueActionRepositoryInterface;
use App\IdentityAccess\Domain\Repository\UtilisateurRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api')]
final class AuditLogController extends AbstractController
{
    public function __construct(
        private readonly HistoriqueActionRepositoryInterface $historiqueActionRepository,
        private readonly UtilisateurRepositoryInterface $utilisateurRepository,
    ) {
    }

    #[Route('/audit-logs', name: 'api_audit_logs_list', methods: ['GET'])]
    #[IsGranted('access.audit.view')]
    public function list(Request $request): JsonResponse
    {
        $utilisateurId = $request->query->get('utilisateur_id');
        $action = $request->query->get('action');
        $from = $request->query->get('from');
        $to = $request->query->get('to');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 50)));

        $fromDate = is_string($from) && $from !== '' ? new \DateTimeImmutable($from) : null;
        $toDate = is_string($to) && $to !== '' ? new \DateTimeImmutable($to) : null;
        $utilisateurUuid = is_string($utilisateurId) && $utilisateurId !== '' ? Uuid::fromString($utilisateurId) : null;
        $excludeUtilisateurId = $this->utilisateurRepository->findSystemAdmin()?->getId();

        $logs = $this->historiqueActionRepository->search(
            utilisateurId: $utilisateurUuid,
            action: is_string($action) ? $action : null,
            from: $fromDate,
            to: $toDate,
            page: $page,
            limit: $limit,
            excludeUtilisateurId: $excludeUtilisateurId,
        );

        $total = $this->historiqueActionRepository->countSearch(
            utilisateurId: $utilisateurUuid,
            action: is_string($action) ? $action : null,
            from: $fromDate,
            to: $toDate,
            excludeUtilisateurId: $excludeUtilisateurId,
        );

        $data = array_map(static fn ($log) => [
            'id' => (string) $log->getId(),
            'action' => $log->getAction(),
            'description' => $log->getDescription(),
            'utilisateur_id' => (string) $log->getUtilisateurId(),
            'date_action' => $log->getDateAction()->format(\DateTimeInterface::ATOM),
        ], $logs);

        return $this->json([
            'data' => $data,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ],
        ]);
    }
}
