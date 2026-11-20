<?php

namespace App\Impression\Application\Service;

use App\Client\Application\Service\ClientLogoUploadService;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\Project\Application\Service\ProjectDocumentService;
use App\Project\Domain\Enum\ProjectDocumentType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Symfony\Component\Uid\Uuid;
use Twig\Environment;

final class ProjectDocumentImpressionService
{
    public function __construct(
        private readonly ProjectDocumentService $documents,
        private readonly ClientRepositoryInterface $clients,
        private readonly ImpressionDocumentSupport $support,
        private readonly Environment $twig,
        private readonly ClientLogoUploadService $logos,
    ) {
    }

    public function render(string $projectId, string $id, string $format = 'html', string $page = 'a4', string $orientation = 'portrait', string $disposition = 'inline', ?string $template = null): Response
    {
        $document = $this->documents->get($projectId, $id);
        if ($document->getType() !== ProjectDocumentType::DELIVERY_NOTE) {
            throw new NotFoundHttpException('Impression non disponible pour ce type de document.');
        }
        $template ??= $this->support->settings()['invoice_template'];
        foreach ([[$format, ['html', 'pdf']], [$page, ['a4', 'a5']], [$orientation, ['portrait', 'landscape']], [$disposition, ['inline', 'attachment']], [$template, ['classic', 'modern']]] as [$value, $allowed]) {
            if (!in_array($value, $allowed, true)) {
                throw new \InvalidArgumentException('Paramètre d’impression invalide : '. $value);
            }
        }
        $data = $this->documents->toArray($document);
        $data['title'] = 'Bon de livraison '.$document->getNumber();
        $data['documentLabel'] = 'Bon de livraison';
        $data['clientName'] = $data['clientTitle'];
        $data['clientLogo'] = $this->clientLogo($document->getClientId());
        $data['projectName'] = $this->documents->project($projectId)->getTitle();
        // The shared modern invoice paginator reads this metadata for continuation pages.
        $data['invoice'] = ['number' => $document->getNumber()];
        $data['dateDisplay'] = $document->getDate()->format('d/m/Y');
        $data['lines'] = $this->printLines($document->getLines());
        $html = $this->twig->render('impression/delivery_note_'.$template.'.html.twig', [
            'data' => $data,
            'profile' => $this->support->profile(),
            'page' => $this->support->pageContext($page, $orientation),
            'render_mode' => $format,
            'auto_print' => $format === 'html' && $disposition === 'inline',
        ]);
        $filename = 'bon-livraison-'.(preg_replace('/[^a-zA-Z0-9._-]+/', '-', $document->getNumber()) ?: 'document');

        return $format === 'pdf'
            ? $this->support->pdfResponse($html, $filename, $page, $orientation, $disposition)
            : $this->support->htmlResponse($html, $filename, $disposition);
    }

    /** Bound table cell heights because Dompdf cannot split a cell across pages. */
    private function printLines(array $lines): array
    {
        $result = [];
        foreach ($lines as $line) {
            $parts = [];
            foreach (['description' => 38, 'observation' => 15] as $field => $width) {
                $wrapped = [];
                foreach (preg_split('/\R/u', $line[$field]) ?: [''] as $paragraph) {
                    foreach (mb_str_split($paragraph ?: ' ', $width) as $fragment) {
                        $wrapped[] = $fragment;
                    }
                }
                $parts[$field] = array_map(static fn (array $chunk) => implode("\n", $chunk), array_chunk($wrapped, 6));
            }
            $count = max(count($parts['description']), count($parts['observation']));
            for ($i = 0; $i < $count; ++$i) {
                $result[] = [
                    'description' => $parts['description'][$i] ?? '',
                    'quantity' => $i === 0 ? str_replace('.', ',', (string) $line['quantity']) : '',
                    'observation' => $parts['observation'][$i] ?? '',
                ];
            }
        }

        return $result;
    }

    private function clientLogo(Uuid $clientId): ?string
    {
        $client = $this->clients->findById($clientId);

        return $client !== null && $client->isEnabled()
            ? $this->logos->resolveForDocuments($client->getLogoUrl())
            : null;
    }
}
