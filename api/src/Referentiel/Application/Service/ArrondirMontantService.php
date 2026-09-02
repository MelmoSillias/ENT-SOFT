<?php

namespace App\Referentiel\Application\Service;

use App\Referentiel\Domain\Entity\Devise;
use App\Referentiel\Domain\Enum\ModeArrondi;
use App\Referentiel\Domain\Repository\DeviseRepositoryInterface;

final class ArrondirMontantService
{
    public function __construct(
        private readonly DeviseRepositoryInterface $deviseRepository,
    ) {
    }

    public function arrondir(string $montant, string $deviseCode): string
    {
        $devise = $this->deviseRepository->findByCode($deviseCode);
        if (null === $devise) {
            return number_format(round((float) $montant, 2), 2, '.', '');
        }

        return $this->arrondirPourDevise($montant, $devise);
    }

    public function arrondirPourDevise(string $montant, Devise $devise): string
    {
        $value = (float) $montant;

        if ($devise->getModeArrondi() === ModeArrondi::UNITE) {
            $unite = (float) ($devise->getUniteArrondi() ?? '1');
            if ($unite <= 0) {
                $unite = 1.0;
            }
            $rounded = round($value / $unite, 0, PHP_ROUND_HALF_UP) * $unite;

            return number_format($rounded, 0, '.', '');
        }

        $mode = match ($devise->getModeArrondi()) {
            ModeArrondi::HALF_DOWN => PHP_ROUND_HALF_DOWN,
            ModeArrondi::HALF_EVEN => PHP_ROUND_HALF_EVEN,
            ModeArrondi::UP => PHP_ROUND_HALF_UP,
            ModeArrondi::DOWN => PHP_ROUND_HALF_DOWN,
            default => PHP_ROUND_HALF_UP,
        };

        $scale = $devise->getDecimales();
        $rounded = round($value, $scale, $mode);

        return number_format($rounded, $scale, '.', '');
    }

    /** @return array{montant: string, label: string} */
    public function formater(string $montant, string $deviseCode): array
    {
        $devise = $this->deviseRepository->findByCode($deviseCode);
        $arrondi = $this->arrondir($montant, $deviseCode);

        if (null === $devise) {
            return ['montant' => $arrondi, 'label' => $arrondi.' '.$deviseCode];
        }

        $numeric = (float) $arrondi;
        $formatted = number_format(
            $numeric,
            $devise->getDecimales(),
            ',',
            $devise->getDecimales() === 0 ? '.' : ' ',
        );

        $symbole = $devise->getSymbole() ?? $devise->getCode();
        $nom = $devise->getNom();

        if (in_array($devise->getCode(), ['XOF', 'XAF'], true)) {
            return ['montant' => $arrondi, 'label' => $formatted.' '.$nom];
        }

        return ['montant' => $arrondi, 'label' => $formatted.' '.$symbole];
    }
}
