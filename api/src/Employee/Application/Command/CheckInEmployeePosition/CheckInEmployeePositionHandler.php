<?php

namespace App\Employee\Application\Command\CheckInEmployeePosition;

use App\Employee\Application\Dto\EmployeePositionResponseDto;
use App\Employee\Domain\Entity\EmployeePosition;
use App\Employee\Domain\Repository\EmployeePositionRepositoryInterface;
use App\Employee\Domain\Repository\EmployeeRepositoryInterface;
use App\Geo\Domain\ValueObject\GeoPoint;
use Symfony\Component\Uid\Uuid;

final class CheckInEmployeePositionHandler
{
    private const MAX_POSITIONS_PER_EMPLOYEE = 10;

    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository,
        private readonly EmployeePositionRepositoryInterface $positionRepository,
    ) {
    }

    public function handle(CheckInEmployeePositionCommand $command): EmployeePositionResponseDto
    {
        $employee = $this->employeeRepository->findByUserId(Uuid::fromString($command->userId));
        if (null === $employee || !$employee->isEnabled()) {
            throw new \InvalidArgumentException(
                'Aucun employé lié à votre compte. Contactez un administrateur.',
            );
        }

        $point = GeoPoint::tryFrom($command->latitude, $command->longitude);
        if (null === $point) {
            throw new \InvalidArgumentException('Latitude et longitude sont requises.');
        }

        $accuracy = $command->accuracy;
        if (null !== $accuracy && (!\is_finite($accuracy) || $accuracy < 0)) {
            throw new \InvalidArgumentException('Précision GPS invalide.');
        }

        $position = new EmployeePosition(
            $employee->getId(),
            $point->latitude,
            $point->longitude,
            $accuracy,
        );
        $this->positionRepository->save($position);
        $this->positionRepository->pruneOlderThan($employee->getId(), self::MAX_POSITIONS_PER_EMPLOYEE);

        return EmployeePositionResponseDto::fromEntity($position, $employee);
    }
}
