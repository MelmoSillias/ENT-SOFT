<?php

namespace App\Configuration\Application\Service;

use App\Configuration\Domain\Entity\HistoriqueSetting;
use App\Configuration\Domain\Entity\Setting;
use App\Configuration\Domain\Repository\HistoriqueSettingRepositoryInterface;
use App\Configuration\Domain\Repository\SettingRepositoryInterface;
use App\IdentityAccess\Domain\Entity\Utilisateur;
use App\SharedKernel\Application\Event\DomainEventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AgenceLogoUploadService
{
    public const SETTING_KEY = 'AGENCE_LOGO_URL';
    public const PUBLIC_PATH_PREFIX = '/uploads/logos/';
    private const RELATIVE_DIR = 'public/uploads/logos';
    private const MAX_SIZE = '2M';

    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    /** @var array<string, string> */
    private const MIME_TO_EXTENSION = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function __construct(
        private readonly SettingRepositoryInterface $settingRepository,
        private readonly HistoriqueSettingRepositoryInterface $historiqueSettingRepository,
        private readonly DomainEventDispatcherInterface $eventDispatcher,
        private readonly ValidatorInterface $validator,
        private readonly string $projectDir,
    ) {
    }

    public function upload(UploadedFile $file, Utilisateur $utilisateur): Setting
    {
        $this->assertValidImage($file);

        $mime = (string) $file->getMimeType();
        $extension = self::MIME_TO_EXTENSION[$mime] ?? null;
        if (null === $extension) {
            throw new \InvalidArgumentException('Seules les images JPEG, PNG, WebP et GIF sont acceptées.');
        }

        $directory = $this->projectDir.\DIRECTORY_SEPARATOR.str_replace('/', \DIRECTORY_SEPARATOR, self::RELATIVE_DIR);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de créer le dossier de stockage du logo.');
        }

        $this->removeExistingLogoFiles($directory);

        $filename = 'agence-logo.'.$extension;
        $file->move($directory, $filename);

        $publicUrl = self::PUBLIC_PATH_PREFIX.$filename;

        return $this->persistSettingValue($publicUrl, $utilisateur, 'Upload logo agence');
    }

    public function clear(Utilisateur $utilisateur): Setting
    {
        $directory = $this->projectDir.\DIRECTORY_SEPARATOR.str_replace('/', \DIRECTORY_SEPARATOR, self::RELATIVE_DIR);
        if (is_dir($directory)) {
            $this->removeExistingLogoFiles($directory);
        }

        return $this->persistSettingValue('', $utilisateur, 'Suppression logo agence');
    }

    public function resolveForDocuments(?string $logoUrl): ?string
    {
        if (null === $logoUrl || '' === trim($logoUrl)) {
            return null;
        }

        $logoUrl = trim($logoUrl);
        if (!str_starts_with($logoUrl, self::PUBLIC_PATH_PREFIX)) {
            return $logoUrl;
        }

        $relative = ltrim(parse_url($logoUrl, PHP_URL_PATH) ?: $logoUrl, '/');
        $absolute = $this->projectDir.\DIRECTORY_SEPARATOR.'public'.\DIRECTORY_SEPARATOR.str_replace('/', \DIRECTORY_SEPARATOR, $relative);
        if (!is_file($absolute)) {
            return $logoUrl;
        }

        $mime = mime_content_type($absolute) ?: 'application/octet-stream';
        if (!str_starts_with($mime, 'image/')) {
            return $logoUrl;
        }

        $binary = file_get_contents($absolute);
        if (false === $binary) {
            return $logoUrl;
        }

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }

    private function assertValidImage(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Fichier invalide ou upload incomplet.');
        }

        $violations = $this->validator->validate($file, [
            new Assert\NotBlank(message: 'Aucun fichier reçu.'),
            new Assert\Image(
                maxSize: self::MAX_SIZE,
                mimeTypes: self::ALLOWED_MIME_TYPES,
                mimeTypesMessage: 'Seules les images JPEG, PNG, WebP et GIF sont acceptées.',
                maxSizeMessage: 'Le logo ne doit pas dépasser {{ limit }} {{ suffix }}.',
            ),
        ]);

        if (\count($violations) > 0) {
            throw new \InvalidArgumentException((string) $violations->get(0)->getMessage());
        }

        $mime = (string) $file->getMimeType();
        if (!isset(self::MIME_TO_EXTENSION[$mime])) {
            throw new \InvalidArgumentException('Seules les images JPEG, PNG, WebP et GIF sont acceptées.');
        }
    }

    private function persistSettingValue(string $valeur, Utilisateur $utilisateur, string $motif): Setting
    {
        $setting = $this->settingRepository->findByCle(self::SETTING_KEY);
        if (null === $setting) {
            throw new \RuntimeException('Setting AGENCE_LOGO_URL introuvable.');
        }

        $ancienneValeur = $setting->getValeur();
        $setting->modifier($valeur, $utilisateur->getId(), $motif);

        $this->historiqueSettingRepository->save(new HistoriqueSetting(
            $setting->getCle(),
            $ancienneValeur,
            $valeur,
            $utilisateur->getId(),
            $motif,
        ), false);

        $this->settingRepository->save($setting, false);

        foreach ($setting->pullDomainEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        $this->settingRepository->save($setting);

        return $setting;
    }

    private function removeExistingLogoFiles(string $directory): void
    {
        foreach (glob($directory.\DIRECTORY_SEPARATOR.'agence-logo.*') ?: [] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
