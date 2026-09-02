<?php

namespace App\AccessAudit\Application\EventListener;

use App\AccessAudit\Domain\Entity\HistoriqueAction;
use App\AccessAudit\Domain\Repository\HistoriqueActionRepositoryInterface;
use App\Configuration\Domain\Event\SettingModifie;
use App\SharedKernel\Domain\Event\DomainEventInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Uid\Uuid;

final class HistoriqueActionListener
{
    public function __construct(
        private readonly HistoriqueActionRepositoryInterface $historiqueActionRepository,
    ) {
    }

    #[AsEventListener(event: SettingModifie::class)]
    public function onSettingModifie(SettingModifie $event): void
    {
        $this->log(
            action: 'MODIFICATION_SETTING',
            utilisateurId: $event->utilisateurId(),
            description: sprintf(
                'Setting %s modifié : %s → %s',
                $event->cle(),
                $event->ancienneValeur(),
                $event->nouvelleValeur(),
            ),
        );
    }

    public function onDomainEvent(DomainEventInterface $event, Uuid $utilisateurId, ?string $description = null): void
    {
        $action = $this->resolveActionName($event);
        $this->log($action, $utilisateurId, $description);
    }

    private function log(string $action, Uuid $utilisateurId, ?string $description): void
    {
        $this->historiqueActionRepository->save(
            new HistoriqueAction($action, $utilisateurId, $description),
        );
    }

    private function resolveActionName(DomainEventInterface $event): string
    {
        $className = $event::class;
        $shortName = substr($className, strrpos($className, '\\') + 1);

        return strtoupper(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName) ?? $shortName);
    }
}
