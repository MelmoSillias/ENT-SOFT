<?php

namespace App\DataImport\Application;

use App\Client\Application\Command\CreateClient\{CreateClientCommand, CreateClientHandler};
use App\Client\Domain\Entity\Client;
use App\DataImport\Domain\Entity\ImportRecord;
use App\Finance\Application\Command\CreateFinancialTransaction\{CreateFinancialTransactionCommand, CreateFinancialTransactionHandler};
use App\Finance\Domain\Enum\TransactionStatus;
use App\Geo\Domain\ValueObject\GeoPoint;
use App\Prestataire\Application\Command\CreatePrestataire\{CreatePrestataireCommand, CreatePrestataireHandler};
use App\Prestataire\Application\Command\CreatePrestation\{CreatePrestationCommand, CreatePrestationHandler};
use App\Prestataire\Domain\Entity\Prestataire;
use App\Prestataire\Domain\Enum\PrestationWorkStatus;
use App\SharedKernel\Domain\Validation\FieldValidator;
use App\Site\Application\Command\CreateSite\{CreateSiteCommand, CreateSiteHandler};
use App\Site\Domain\Entity\Site;
use App\Stock\Application\Command\CreateEquipment\{CreateEquipmentCommand, CreateEquipmentHandler};
use App\Stock\Domain\Enum\EquipmentUnit;
use Doctrine\ORM\EntityManagerInterface;

final class ImportRowFactory
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ImportFormat $format,
        private readonly CreateClientHandler $clients,
        private readonly CreateSiteHandler $sites,
        private readonly CreatePrestataireHandler $prestataires,
        private readonly CreatePrestationHandler $prestations,
        private readonly CreateFinancialTransactionHandler $transactions,
        private readonly CreateEquipmentHandler $equipements,
    ) {}

    public static function key(string $type, string $externalId): string
    {
        return hash('sha256', $type.':'.$externalId);
    }

    public function prepare(string $type, array $row): object
    {
        foreach ($this->format->definition($type)['required'] as $field) {
            FieldValidator::requireNonEmpty($row[$field], $field);
        }
        if (!preg_match('/^[A-Za-z0-9._-]{1,100}$/D', $row['id_import'])) {
            throw new \InvalidArgumentException('id_import : 1 à 100 lettres, chiffres, points, tirets ou underscores.');
        }
        foreach ($row as $field => $value) {
            $max = match ($field) {
                'code', 'client_code', 'site_code' => 100,
                'prenom', 'nom', 'ville', 'boite_postale', 'categorie' => 100,
                'description' => 10000,
                'pieces_jointes' => 5000,
                default => 255,
            };
            if (mb_strlen($value) > $max) {
                throw new \InvalidArgumentException(sprintf('%s : %d caractères maximum.', $field, $max));
            }
        }
        $optional = static fn (string $field) => ($row[$field] ?? '') !== '' ? $row[$field] : null;
        $clientId = isset($row['client_code']) ? $this->reference('clients', Client::class, 'code', $row['client_code']) : null;
        $siteId = isset($row['site_code']) ? $this->reference('sites', Site::class, 'code', $row['site_code']) : null;

        if ($type === 'sites') {
            if (mb_strlen($row['code']) > 50 || $this->em->getRepository(Site::class)->findOneBy(['code' => $row['code']])) {
                throw new \InvalidArgumentException('Code site déjà utilisé ou supérieur à 50 caractères.');
            }
            $lat = $optional('latitude') !== null ? $this->number($row['latitude'], 'latitude') : null;
            $lng = $optional('longitude') !== null ? $this->number($row['longitude'], 'longitude') : null;
            GeoPoint::tryFrom($lat, $lng);
            return new CreateSiteCommand($row['code'], $row['titre'], $optional('description'), $clientId, $lat, $lng);
        }
        if ($type === 'clients') {
            return new CreateClientCommand($row['titre'], $optional('description'), $optional('adresse'), $optional('boite_postale'), $optional('ville'));
        }
        if ($type === 'prestataires') {
            if (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Adresse email invalide.');
            }
            if ($this->em->getRepository(Prestataire::class)->findOneBy(['email' => $row['email']])) {
                throw new \InvalidArgumentException('Un prestataire utilise déjà cet email.');
            }
            return new CreatePrestataireCommand($row['prenom'], $row['nom'], $row['email'], FieldValidator::requirePhone($row['telephone']), $optional('adresse'));
        }
        if ($type === 'equipements') {
            $unit = $optional('unite') ?? 'unit';
            if (!EquipmentUnit::tryFrom($unit)) {
                throw new \InvalidArgumentException('Unité inconnue : '.implode(', ', array_column(EquipmentUnit::cases(), 'value')));
            }
            return new CreateEquipmentCommand($row['titre'], $optional('description'), $unit, $clientId);
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $row['date']);
        if (!$date || $date->format('Y-m-d') !== $row['date']) {
            throw new \InvalidArgumentException('Date invalide : utilisez AAAA-MM-JJ.');
        }
        $amount = $this->number($row['montant'], 'montant');
        if ($amount <= 0 || $amount > 999999999999.99) {
            throw new \InvalidArgumentException('Le montant doit être positif et inférieur à 1 000 milliards.');
        }
        if ($type === 'prestations') {
            $prestataireId = $this->reference('prestataires', Prestataire::class, 'email', $row['prestataire_email']);
            $status = $optional('statut_travail') ?? 'pending';
            if (!PrestationWorkStatus::tryFrom($status)) {
                throw new \InvalidArgumentException('statut_travail : pending, in_progress ou completed.');
            }
            return new CreatePrestationCommand($prestataireId, $row['description'], $amount, $row['date'], $siteId, $status);
        }
        $status = $type === 'depenses' ? 'completed' : ($optional('statut') ?? 'pending');
        if (!TransactionStatus::tryFrom($status)) {
            throw new \InvalidArgumentException('statut : pending, completed ou cancelled.');
        }
        return new CreateFinancialTransactionCommand(
            date: $row['date'], amount: $amount, type: $type === 'depenses' ? 'expense' : 'income',
            category: $row['categorie'], status: $status, fromParty: $row['emetteur'], toParty: $row['beneficiaire'],
            description: $optional('description'), clientId: $clientId, siteId: $siteId,
        );
    }

    public function create(string $type, object $command): array
    {
        $handler = match ($type) {
            'clients' => $this->clients, 'sites' => $this->sites, 'prestataires' => $this->prestataires,
            'prestations' => $this->prestations, 'equipements' => $this->equipements,
            'depenses', 'revenus' => $this->transactions,
        };
        return $handler->handle($command)->toArray();
    }

    private function number(string $value, string $field): float
    {
        if (!preg_match('/^-?\d+(?:[.,]\d+)?$/D', $value)) {
            throw new \InvalidArgumentException($field.' : nombre attendu, sans séparateur de milliers.');
        }
        $number = (float) str_replace(',', '.', $value);
        if (!is_finite($number)) {
            throw new \InvalidArgumentException($field.' : valeur trop grande.');
        }
        return $number;
    }

    private function reference(string $type, string $class, string $field, string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        if (str_starts_with($value, '@')) {
            $record = $this->em->find(ImportRecord::class, self::key($type, substr($value, 1)));
            $entity = $record ? $this->em->find($class, $record->getEntityId()) : null;
        } else {
            $entities = $this->em->getRepository($class)->findBy([$field => $value, 'isEnabled' => true]);
            if (count($entities) > 1) {
                throw new \InvalidArgumentException('Référence ambiguë : '.$value.'. Utilisez @id_import.');
            }
            $entity = $entities[0] ?? null;
        }
        if (!$entity || !$entity->isEnabled()) {
            throw new \InvalidArgumentException('Référence introuvable ou désactivée : '.$value.'. Importez d’abord les '.$type.'.');
        }
        return (string) $entity->getId();
    }
}
