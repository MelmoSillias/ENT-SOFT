<?php

namespace App\DataImport\Application;

use App\DataImport\Domain\Entity\ImportRecord;
use App\Document\Domain\Entity\Document;
use App\Document\Domain\Enum\DocumentOwnerType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Uid\Uuid;

final class DataImportService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ImportFormat $format,
        private readonly ImportRowFactory $factory,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {}

    public function run(string $type, array $package, bool $execute, bool $canUpload): array
    {
        $rows = $this->format->parse($type, $package['csv']);
        $report = ['valid' => true, 'created' => 0, 'ready' => 0, 'skipped' => 0, 'errors' => 0, 'rows' => []];
        $prepared = [];
        $seen = [];
        $naturalKeys = [];
        $usedFiles = [];
        foreach ($rows as $entry) {
            $row = $entry['data'];
            $result = ['line' => $entry['line'], 'idImport' => $row['id_import'], 'status' => 'ready', 'message' => 'Prêt à importer', 'entityId' => null, 'attachments' => 0];
            try {
                $key = ImportRowFactory::key($type, $row['id_import']);
                if (isset($seen[$key])) {
                    throw new \InvalidArgumentException('id_import présent plusieurs fois dans le fichier.');
                }
                $seen[$key] = true;
                $paths = ($row['pieces_jointes'] ?? '') === '' ? [] : array_map('trim', explode('|', $row['pieces_jointes']));
                if (count($paths) !== count(array_unique($paths))) {
                    throw new \InvalidArgumentException('Justificatif indiqué plusieurs fois sur la même ligne.');
                }
                $hashes = [];
                foreach ($paths as $path) {
                    if (!isset($package['files'][$path])) {
                        throw new \InvalidArgumentException('Pièce jointe absente du ZIP : '.$path);
                    }
                    $usedFiles[$path] = true;
                    $hashes[$path] = hash('sha256', $package['files'][$path]);
                }
                $result['attachments'] = count($paths);
                ksort($hashes);
                $fingerprint = hash('sha256', json_encode([$row, $hashes], JSON_THROW_ON_ERROR));
                $record = $this->em->find(ImportRecord::class, $key);
                if ($record) {
                    if ($record->getFingerprint() !== $fingerprint) {
                        throw new \InvalidArgumentException('Cet id_import a déjà été utilisé avec un contenu différent. Modifiez la fiche existante dans le logiciel.');
                    }
                    $result['status'] = 'skipped';
                    $result['message'] = 'Déjà importé, ignoré';
                    $result['entityId'] = $record->getEntityId();
                    ++$report['skipped'];
                } else {
                    if ($paths && !$canUpload) {
                        throw new \InvalidArgumentException('La permission document.documents.upload est requise pour les justificatifs.');
                    }
                    $command = $this->factory->prepare($type, $row);
                    $natural = match ($type) { 'sites' => $row['code'], 'prestataires' => strtolower($row['email']), default => null };
                    if ($natural !== null && isset($naturalKeys[$natural])) {
                        throw new \InvalidArgumentException('Code site ou email prestataire présent plusieurs fois dans le fichier.');
                    }
                    if ($natural !== null) { $naturalKeys[$natural] = true; }
                    $prepared[] = ['index' => count($report['rows']), 'command' => $command, 'paths' => $paths, 'key' => $key, 'fingerprint' => $fingerprint];
                    ++$report['ready'];
                }
            } catch (\InvalidArgumentException $e) {
                $result['status'] = 'error';
                $result['message'] = $e->getMessage();
                ++$report['errors'];
            }
            $report['rows'][] = $result;
        }
        $unused = array_diff(array_keys($package['files']), array_keys($usedFiles));
        if ($unused) {
            throw new \InvalidArgumentException('Pièces du ZIP non référencées dans pieces_jointes : '.implode(', ', $unused));
        }
        $report['valid'] = $report['errors'] === 0;
        if (!$execute || !$report['valid'] || !$prepared) {
            return $report;
        }
        $written = [];
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            foreach ($prepared as $item) {
                $entity = $this->factory->create($type, $item['command']);
                foreach ($item['paths'] as $path) {
                    $directory = $this->projectDir.'/public/uploads/documents';
                    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                        throw new \RuntimeException('Stockage des justificatifs indisponible.');
                    }
                    $filename = Uuid::v7()->toRfc4122().'.'.strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    $absolute = $directory.'/'.$filename;
                    $written[] = $absolute;
                    if (file_put_contents($absolute, $package['files'][$path]) !== strlen($package['files'][$path])) {
                        throw new \RuntimeException('Échec d’écriture du justificatif.');
                    }
                    $this->em->persist(new Document(basename($path), basename($path), '/uploads/documents/'.$filename, DocumentOwnerType::FINANCIAL_TRANSACTION, Uuid::fromString($entity['id'])));
                }
                $this->em->persist(new ImportRecord($item['key'], $item['fingerprint'], $entity['id']));
                $report['rows'][$item['index']]['status'] = 'created';
                $report['rows'][$item['index']]['message'] = 'Importé';
                $report['rows'][$item['index']]['entityId'] = $entity['id'];
                ++$report['created'];
            }
            $this->em->flush();
            $connection->commit();
            $report['ready'] = 0;
            return $report;
        } catch (\Throwable $e) {
            if ($connection->isTransactionActive()) { $connection->rollBack(); }
            $this->em->clear();
            foreach ($written as $path) { if (is_file($path)) { unlink($path); } }
            throw new HttpException(500, 'L’import a été annulé : aucune ligne du lot n’a été enregistrée. Relancez le contrôle avant de réessayer.', $e);
        }
    }
}
