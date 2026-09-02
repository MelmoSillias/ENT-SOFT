<?php

namespace App\Referentiel\Application\Service;

use App\Configuration\Domain\Repository\SettingRepositoryInterface;
use App\Referentiel\Domain\Enum\ReferenceSequenceType;

/**
 * @phpstan-type ReferenceFormatConfig array{prefixe: string, nbChiffres: int, titreRecu: string}
 */
final class ReferenceSettingsReader
{
    private const MIN_CHIFFRES = 1;
    private const MAX_CHIFFRES = 8;
    private const MAX_PREFIXE_LENGTH = 15;
    private const MAX_TITRE_LENGTH = 60;

    public function __construct(
        private readonly SettingRepositoryInterface $settingRepository,
    ) {
    }

    /** @return ReferenceFormatConfig */
    public function lire(ReferenceSequenceType $type): array
    {
        $base = $type->settingPrefixKey();

        return [
            'prefixe' => $this->lirePrefixe($base),
            'nbChiffres' => $this->lireNbChiffres($base),
            'titreRecu' => $this->lireTitreRecu($base),
        ];
    }

    public function formater(ReferenceSequenceType $type, int $sequence): string
    {
        $config = $this->lire($type);

        return $config['prefixe'].str_pad((string) $sequence, $config['nbChiffres'], '0', STR_PAD_LEFT);
    }

    public function titreRecu(ReferenceSequenceType $type): string
    {
        return $this->lire($type)['titreRecu'];
    }

    public function titreRecuPourKind(string $kind): string
    {
        return 'RECU';
    }

    private function lirePrefixe(string $base): string
    {
        $value = $this->settingRepository->findByCle($base.'_PREFIXE')?->getValeur() ?? '';

        return mb_substr(trim($value), 0, self::MAX_PREFIXE_LENGTH);
    }

    private function lireNbChiffres(string $base): int
    {
        $raw = $this->settingRepository->findByCle($base.'_NB_CHIFFRES')?->getValeur() ?? '3';
        $value = (int) $raw;

        return max(self::MIN_CHIFFRES, min(self::MAX_CHIFFRES, $value));
    }

    private function lireTitreRecu(string $base): string
    {
        $value = trim($this->settingRepository->findByCle($base.'_TITRE_RECU')?->getValeur() ?? '');
        if ($value === '') {
            return 'RECU';
        }

        return mb_substr($value, 0, self::MAX_TITRE_LENGTH);
    }
}
