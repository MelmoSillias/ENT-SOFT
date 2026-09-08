<?php

namespace App\Upload\Application\Service;

use App\Upload\Application\Dto\ClaimedUpload;
use App\Upload\Domain\Entity\UploadSession;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Point d'intégration réutilisable : un module métier "réclame" un fichier
 * uploadé par chunks et le déplace vers son dossier public cible.
 *
 * Exemple :
 *   $claimed = $claimService->claim($uploadSessionId, 'documents');
 *   // $claimed->publicPath === '/uploads/documents/xxx-file.pdf'
 */
final class UploadedFileClaimService
{
    public function __construct(
        private readonly UploadSessionService $sessions,
        private readonly EntityManagerInterface $em,
        private readonly string $projectDir,
    ) {
    }

    public function claim(string $uploadSessionId, string $targetRelativeDir, string $prefix = 'file_'): ClaimedUpload
    {
        $session = $this->sessions->requireReadyOwned($uploadSessionId);

        $relativeDir = trim(str_replace('\\', '/', $targetRelativeDir), '/');
        $dir = rtrim($this->projectDir.'/public/uploads/'.$relativeDir, '/');
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new BadRequestHttpException('Impossible de créer le dossier de stockage.');
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $session->getOriginalFilename()) ?: 'file.bin';
        $uniqueName = uniqid($prefix, true).'-'.$safeName;
        $target = $dir.'/'.$uniqueName;

        $source = (string) $session->getAssembledPath();
        if (!@rename($source, $target) && !@copy($source, $target)) {
            throw new BadRequestHttpException('Impossible de déplacer le fichier');
        }
        @unlink($source);
        $this->sessions->deleteChunkDir($session);

        $publicPath = '/uploads/'.($relativeDir !== '' ? $relativeDir.'/' : '').$uniqueName;
        $session->setAttachedPath($publicPath);
        $session->setAssembledPath(null);
        $session->setStatus(UploadSession::STATUS_ATTACHED);
        $this->em->flush();

        return new ClaimedUpload(
            publicPath: $publicPath,
            storedFilename: $uniqueName,
            originalFilename: $session->getOriginalFilename(),
            mimeType: $session->getMimeType(),
            byteSize: $session->getByteSize(),
        );
    }
}
