<?php

namespace App\Project\Presentation\Api\Controller;

use App\Document\Application\Command\DeleteDocument\DeleteDocumentCommand;
use App\Document\Application\Command\DeleteDocument\DeleteDocumentHandler;
use App\Document\Domain\Enum\DocumentOwnerType;
use App\Document\Domain\Repository\DocumentRepositoryInterface;
use App\IdentityAccess\Domain\Entity\Utilisateur;
use App\Impression\Application\Service\ActivityReportImpressionService;
use App\Project\Application\Command\ActivityReport\ActivityReportCommand;
use App\Project\Application\Command\ActivityReport\ActivityReportCommandHandler;
use App\Project\Application\Query\ActivityReport\ActivityReportQuery;
use App\Project\Application\Query\ActivityReport\ActivityReportQueryHandler;
use App\Project\Domain\Repository\ActivityReportRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/project-reports')]
final class ActivityReportController extends AbstractController
{
    #[Route('', name: 'api_project_reports_list', methods: ['GET'])]
    #[IsGranted('project.reports.view')]
    public function list(Request $request, ActivityReportQueryHandler $handler): JsonResponse
    {
        return $this->json($handler->list(new ActivityReportQuery(
            search: $request->query->get('search'),
            from: $request->query->get('from'),
            to: $request->query->get('to'),
            status: $request->query->get('status'),
        )));
    }

    #[Route('/{id}', name: 'api_project_reports_get', methods: ['GET'])]
    #[IsGranted('project.reports.view')]
    public function getReport(string $id, ActivityReportQueryHandler $handler): JsonResponse
    {
        return $this->json($handler->get($id));
    }

    #[Route('/{id}/export', name: 'api_project_reports_export', methods: ['GET'])]
    #[IsGranted('project.reports.export')]
    public function export(string $id, Request $request, ActivityReportImpressionService $service): Response
    {
        return $service->render(
            id: $id,
            format: strtolower((string) $request->query->get('format', 'pdf')),
            bundle: $request->query->getBoolean('bundle'),
        );
    }

    #[Route('/{id}/documents/{documentId}', name: 'api_project_reports_document_delete', methods: ['DELETE'])]
    #[IsGranted('project.reports.update')]
    public function deleteDocument(
        string $id,
        string $documentId,
        ActivityReportRepositoryInterface $reports,
        DocumentRepositoryInterface $documents,
        DeleteDocumentHandler $deleteHandler,
    ): JsonResponse {
        $report = $reports->findById(Uuid::fromString($id));
        $document = $documents->findById(Uuid::fromString($documentId));
        if ($report === null || $document === null || $document->getOwnerType() !== DocumentOwnerType::ACTIVITY_REPORT_SITE) {
            return $this->json(['error' => 'Pièce jointe introuvable.'], Response::HTTP_NOT_FOUND);
        }
        $lineIds = array_map(static fn ($line): string => (string) $line->getId(), $report->getLines()->toArray());
        if (!in_array((string) $document->getOwnerId(), $lineIds, true)) {
            return $this->json(['error' => 'Cette pièce jointe n’appartient pas au rapport.'], Response::HTTP_FORBIDDEN);
        }

        return $this->json($deleteHandler->handle(new DeleteDocumentCommand($documentId))->toArray());
    }

    #[Route('', name: 'api_project_reports_create', methods: ['POST'])]
    #[IsGranted('project.reports.create')]
    public function create(Request $request, ActivityReportCommandHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $user = $this->getUser();
        $authorId = $user instanceof Utilisateur ? (string) $user->getId() : null;

        return $this->json($handler->save($this->command(null, $data, $authorId)), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_project_reports_update', methods: ['PUT', 'PATCH'])]
    #[IsGranted('project.reports.update')]
    public function update(
        string $id,
        Request $request,
        ActivityReportCommandHandler $handler,
        ActivityReportQueryHandler $queryHandler,
    ): JsonResponse {
        $data = $request->toArray();
        if ($request->isMethod('PATCH')) {
            $current = $queryHandler->get($id);
            $data = array_replace($current, $data);
            if (!array_key_exists('lines', $request->toArray()) && !array_key_exists('sites', $request->toArray())) {
                unset($data['lines']);
            }
        }

        return $this->json($handler->save($this->command($id, $data, null)));
    }

    #[Route('/{id}', name: 'api_project_reports_delete', methods: ['DELETE'])]
    #[IsGranted('project.reports.delete')]
    public function delete(string $id, ActivityReportCommandHandler $handler): JsonResponse
    {
        $handler->delete($id);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /** @param array<string, mixed> $data */
    private function command(?string $id, array $data, ?string $authorId): ActivityReportCommand
    {
        $lineKey = array_key_exists('lines', $data) ? 'lines' : (array_key_exists('sites', $data) ? 'sites' : null);
        if ($lineKey !== null && !is_array($data[$lineKey])) {
            throw new \InvalidArgumentException('Les lignes du rapport doivent être une liste.');
        }

        return new ActivityReportCommand(
            id: $id,
            startDate: (string) ($data['startDate'] ?? ''),
            endDate: (string) ($data['endDate'] ?? ''),
            description: array_key_exists('description', $data) ? (string) $data['description'] : null,
            status: (string) ($data['status'] ?? 'draft'),
            authorId: $authorId,
            lines: $lineKey !== null ? $data[$lineKey] : null,
        );
    }
}
