<?php

namespace App\DataImport\Presentation\Api\Controller;

use App\DataImport\Application\{DataImportService, ImportFormat, ImportPackageReader};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/data-import')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class DataImportController extends AbstractController
{
    #[Route('/formats', methods: ['GET'])]
    public function formats(): JsonResponse
    {
        $types = [];
        foreach (ImportFormat::TYPES as $type => $definition) {
            if ($this->isGranted($definition['permission'])) {
                $types[] = ['type' => $type, ...$definition];
            }
        }
        return $this->json(['items' => $types]);
    }

    #[Route('/{type}/template', methods: ['GET'])]
    public function template(string $type, ImportFormat $format): Response
    {
        $this->denyAccessUnlessGranted($format->definition($type)['permission']);
        return new Response($format->template($type), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modele-'.$type.'.csv"',
        ]);
    }

    #[Route('/{type}/{action}', requirements: ['action' => 'preview|execute'], methods: ['POST'])]
    public function import(string $type, string $action, Request $request, ImportFormat $format, ImportPackageReader $reader, DataImportService $service): JsonResponse
    {
        $this->denyAccessUnlessGranted($format->definition($type)['permission']);
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new \InvalidArgumentException('Fichier manquant. Vérifiez aussi les limites d’upload du serveur.');
        }
        $package = $reader->read($file);
        $digest = hash_file('sha256', $file->getPathname());
        if ($action === 'execute' && !hash_equals($digest, (string) $request->request->get('digest', ''))) {
            throw new ConflictHttpException('Le fichier a changé. Relancez le contrôle avant l’import.');
        }
        $report = $service->run($type, $package, $action === 'execute', $this->isGranted('document.documents.upload'));
        return $this->json(['digest' => $digest, ...$report], $action === 'execute' && !$report['valid'] ? 422 : 200);
    }
}
