<?php

namespace App\Tests\Unit\Project;

use App\Project\Application\Service\ActivityReportFactory;
use App\Project\Domain\Entity\ActivityReport;
use App\Project\Domain\Entity\ActivityReportSite;
use App\Project\Domain\Enum\ActivityReportLineStatus;
use App\Project\Domain\Enum\ActivityReportStatus;
use App\Project\Domain\Repository\ActivityReportRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class ActivityReportFactoryTest extends TestCase
{
    public function testGeneratesFrenchDescriptionForDayAndPeriod(): void
    {
        $factory = new ActivityReportFactory($this->createMock(ActivityReportRepositoryInterface::class));
        $day = new \DateTimeImmutable('2026-10-27');

        self::assertSame('Rapport d’activité du 27 octobre 2026', $factory->description(null, $day, $day));
        self::assertSame(
            'Rapport d’activité du 27 octobre 2026 au 2 novembre 2026',
            $factory->description('', $day, new \DateTimeImmutable('2026-11-02')),
        );
        self::assertSame('Description manuelle', $factory->description(' Description manuelle ', $day, $day));
    }

    public function testGeneratesStableReportCodeShape(): void
    {
        $repository = $this->createMock(ActivityReportRepositoryInterface::class);
        $repository->expects(self::once())->method('codeExists')->willReturn(false);
        $factory = new ActivityReportFactory($repository);

        self::assertMatchesRegularExpression('/^RAP-20261027-[A-F0-9]{6}$/', $factory->generateCode(
            new \DateTimeImmutable('2026-10-27'),
        ));
    }

    public function testKeepsExistingLineIdentityWhenEditing(): void
    {
        $factory = new ActivityReportFactory($this->createMock(ActivityReportRepositoryInterface::class));
        $date = new \DateTimeImmutable('2026-10-27');
        $report = new ActivityReport('RAP-TEST', $date, $date, 'Test', ActivityReportStatus::DRAFT);
        $line = new ActivityReportSite(
            report: $report,
            status: ActivityReportLineStatus::PENDING,
            position: 0,
            siteTitleSnapshot: 'Site initial',
        );
        $report->replaceLines([$line]);

        $updated = $factory->lines($report, [[
            'id' => (string) $line->getId(),
            'siteTitle' => 'Site modifié',
            'status' => 'completed',
            'attendees' => [],
        ]]);

        self::assertSame($line, $updated[0]);
        self::assertSame('Site modifié', $line->getSiteTitleSnapshot());
        self::assertSame(ActivityReportLineStatus::COMPLETED, $line->getStatus());
    }
}
