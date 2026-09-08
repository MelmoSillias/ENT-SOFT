<?php

namespace App\Upload\Application\Service;

use App\IdentityAccess\Domain\Entity\Utilisateur;
use App\Upload\Domain\Entity\UploadSession;
use App\Upload\Domain\Repository\UploadSessionRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Gestion des sessions d'upload chunké (fichiers volumineux).
 *
 * Module générique : aucune dépendance vers les modules métier.
 * Un module métier récupère le fichier assemblé via UploadedFileClaimService.
 */
final class UploadSessionService
{
    public const CHUNK_SIZE = 4 * 1024 * 1024;
    public const MAX_FILE_SIZE = 100 * 1024 * 1024;
    public const TTL_HOURS = 24;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UploadSessionRepositoryInterface $repository,
        private readonly Security $security,
        private readonly string $projectDir,
    ) {
    }

    /** @param array<string, mixed> $meta */
    public function create(array $meta): array
    {
        $user = $this->requireUser();
        $filename = trim((string) ($meta['filename'] ?? ''));
        $size = (int) ($meta['size'] ?? 0);
        $mime = trim((string) ($meta['mimeType'] ?? '')) ?: 'application/octet-stream';
        $checksum = $this->normalizeChecksum($meta['checksum'] ?? null);

        if ($filename === '') {
            throw new BadRequestHttpException('filename requis');
        }
        if ($size <= 0) {
            throw new BadRequestHttpException('size invalide');
        }
        if ($size > self::MAX_FILE_SIZE) {
            throw new BadRequestHttpException(sprintf('Fichier trop volumineux (max %d Mo)', (int) (self::MAX_FILE_SIZE / 1024 / 1024)));
        }

        $session = new UploadSession(
            publicId: bin2hex(random_bytes(16)),
            ownerId: $user->getId(),
            originalFilename: $filename,
            mimeType: $mime,
            byteSize: $size,
            checksum: $checksum,
            chunkSize: self::CHUNK_SIZE,
            chunkCount: (int) max(1, (int) ceil($size / self::CHUNK_SIZE)),
            expiresAt: (new \DateTimeImmutable())->modify('+'.self::TTL_HOURS.' hours'),
        );

        $this->repository->save($session);
        $this->ensureChunkDir($session);

        return $this->format($session);
    }

    public function putChunk(string $publicId, int $index, string $binary): array
    {
        $session = $this->requireOwnedOpen($publicId);
        if ($index < 0 || $index >= $session->getChunkCount()) {
            throw new BadRequestHttpException('Index de chunk invalide');
        }

        $expected = $index === $session->getChunkCount() - 1
            ? ($session->getByteSize() - ($session->getChunkSize() * ($session->getChunkCount() - 1)))
            : $session->getChunkSize();
        if (strlen($binary) !== $expected) {
            throw new BadRequestHttpException(sprintf('Taille de chunk invalide (attendu %d octets)', $expected));
        }

        $path = $this->chunkPath($session, $index);
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, $binary);
        $session->markChunkReceived($index);
        $session->setStatus(UploadSession::STATUS_UPLOADING);
        $this->em->flush();

        return $this->format($session);
    }

    public function status(string $publicId): array
    {
        return $this->format($this->requireOwned($publicId));
    }

    public function complete(string $publicId): array
    {
        $session = $this->requireOwned($publicId);
        if ($session->isAttached() || ($session->isReady() && is_file((string) $session->getAssembledPath()))) {
            return $this->format($session);
        }
        if (!$session->isOpen()) {
            throw new BadRequestHttpException('Session non finalisable');
        }

        $received = $session->getReceivedChunks();
        if (count($received) !== $session->getChunkCount()) {
            throw new BadRequestHttpException(sprintf('Chunks incomplets (%d/%d)', count($received), $session->getChunkCount()));
        }

        $assembled = $this->assemble($session);
        $size = filesize($assembled);
        if ($size !== $session->getByteSize()) {
            @unlink($assembled);
            $session->setStatus(UploadSession::STATUS_FAILED);
            $this->em->flush();
            throw new BadRequestHttpException('Taille assemblée incohérente');
        }
        if ($session->getChecksum()) {
            $hash = hash_file('sha256', $assembled);
            if (!hash_equals($session->getChecksum(), (string) $hash)) {
                @unlink($assembled);
                $session->setStatus(UploadSession::STATUS_FAILED);
                $this->em->flush();
                throw new BadRequestHttpException('Checksum SHA-256 invalide');
            }
        }

        $session->setAssembledPath($assembled);
        $session->setStatus(UploadSession::STATUS_READY);
        $this->em->flush();

        return $this->format($session);
    }

    public function abort(string $publicId): array
    {
        $session = $this->requireOwned($publicId);
        if ($session->isAttached()) {
            throw new BadRequestHttpException('Fichier déjà rattaché');
        }
        $this->deleteStaging($session);
        $session->setStatus(UploadSession::STATUS_ABORTED);
        $this->em->flush();

        return ['success' => true];
    }

    public function purgeExpired(): int
    {
        $expired = $this->repository->findExpired(new \DateTimeImmutable());
        $count = 0;
        foreach ($expired as $session) {
            $this->deleteStaging($session);
            $this->em->remove($session);
            ++$count;
        }
        if ($count > 0) {
            $this->em->flush();
        }

        return $count;
    }

    /**
     * Récupère une session prête appartenant à l'utilisateur courant.
     * Réservé aux services applicatifs (UploadedFileClaimService).
     */
    public function requireReadyOwned(string $publicId): UploadSession
    {
        $session = $this->requireOwned($publicId);
        if (!$session->isReady() || !is_file((string) $session->getAssembledPath())) {
            throw new BadRequestHttpException('Session d\'upload non finalisée ou fichier assemblé introuvable');
        }

        return $session;
    }

    public function deleteChunkDir(UploadSession $session): void
    {
        $dir = $this->chunkDir($session);
        if (!is_dir($dir)) {
            return;
        }
        $files = glob($dir.'/chunk_*') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }

    /** @return array<string, mixed> */
    public function format(UploadSession $session): array
    {
        return [
            'id' => $session->getPublicId(),
            'filename' => $session->getOriginalFilename(),
            'mimeType' => $session->getMimeType(),
            'size' => $session->getByteSize(),
            'chunkSize' => $session->getChunkSize(),
            'chunkCount' => $session->getChunkCount(),
            'receivedChunks' => $session->getReceivedChunks(),
            'bytesReceived' => $session->receivedBytes(),
            'status' => $session->getStatus(),
            'expiresAt' => $session->getExpiresAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function assemble(UploadSession $session): string
    {
        $dir = $this->chunkDir($session);
        $target = $dir.'/assembled.bin';
        $out = fopen($target, 'wb');
        if ($out === false) {
            throw new BadRequestHttpException('Impossible d\'assembler le fichier');
        }
        for ($i = 0; $i < $session->getChunkCount(); ++$i) {
            $chunk = $this->chunkPath($session, $i);
            if (!is_file($chunk)) {
                fclose($out);
                throw new BadRequestHttpException('Chunk manquant: '.$i);
            }
            $in = fopen($chunk, 'rb');
            if ($in === false) {
                fclose($out);
                throw new BadRequestHttpException('Lecture chunk impossible');
            }
            stream_copy_to_stream($in, $out);
            fclose($in);
        }
        fclose($out);

        return $target;
    }

    private function chunkDir(UploadSession $session): string
    {
        return $this->projectDir.'/var/uploads_staging/'.$session->getPublicId();
    }

    private function chunkPath(UploadSession $session, int $index): string
    {
        return $this->chunkDir($session).'/chunk_'.$index;
    }

    private function ensureChunkDir(UploadSession $session): void
    {
        $dir = $this->chunkDir($session);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    private function deleteStaging(UploadSession $session): void
    {
        if ($session->getAssembledPath() && is_file($session->getAssembledPath())) {
            @unlink($session->getAssembledPath());
        }
        $this->deleteChunkDir($session);
    }

    private function requireUser(): Utilisateur
    {
        $user = $this->security->getUser();
        if (!$user instanceof Utilisateur) {
            throw new AccessDeniedHttpException('Non authentifié');
        }

        return $user;
    }

    private function requireOwned(string $publicId): UploadSession
    {
        $session = $this->repository->findOwned($publicId, $this->requireUser()->getId());
        if (!$session) {
            throw new NotFoundHttpException('Session d\'upload introuvable');
        }

        return $session;
    }

    private function requireOwnedOpen(string $publicId): UploadSession
    {
        $session = $this->requireOwned($publicId);
        if (!$session->isOpen()) {
            throw new BadRequestHttpException('Session d\'upload close');
        }

        return $session;
    }

    private function normalizeChecksum(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $hash = strtolower(trim($value));
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            throw new BadRequestHttpException('checksum SHA-256 invalide');
        }

        return $hash;
    }
}
