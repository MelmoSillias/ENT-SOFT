<?php

namespace App\DataImport\Application;

final class ImportFormat
{
    public const TYPES = [
        'clients' => ['label' => 'Clients', 'permission' => 'client.clients.create', 'required' => ['id_import', 'titre'], 'optional' => ['description', 'adresse', 'boite_postale', 'ville']],
        'sites' => ['label' => 'Sites', 'permission' => 'site.sites.create', 'required' => ['id_import', 'code', 'titre'], 'optional' => ['description', 'client_code', 'latitude', 'longitude']],
        'prestataires' => ['label' => 'Prestataires', 'permission' => 'employee.prestataires.create', 'required' => ['id_import', 'prenom', 'nom', 'email', 'telephone'], 'optional' => ['adresse']],
        'prestations' => ['label' => 'Prestations', 'permission' => 'employee.prestataires.create', 'required' => ['id_import', 'prestataire_email', 'description', 'montant', 'date'], 'optional' => ['site_code', 'statut_travail']],
        'depenses' => ['label' => 'Dépenses', 'permission' => 'finance.transactions.create', 'required' => ['id_import', 'date', 'montant', 'categorie', 'emetteur', 'beneficiaire'], 'optional' => ['description', 'client_code', 'site_code', 'pieces_jointes']],
        'revenus' => ['label' => 'Revenus', 'permission' => 'finance.transactions.create', 'required' => ['id_import', 'date', 'montant', 'categorie', 'emetteur', 'beneficiaire'], 'optional' => ['description', 'client_code', 'site_code', 'statut', 'pieces_jointes']],
        'equipements' => ['label' => 'Équipements', 'permission' => 'stock.equipment.create', 'required' => ['id_import', 'titre'], 'optional' => ['description', 'unite', 'client_code']],
    ];

    public function definition(string $type): array
    {
        return self::TYPES[$type] ?? throw new \InvalidArgumentException('Type d’import inconnu.');
    }

    public function template(string $type): string
    {
        $definition = $this->definition($type);
        return "\xEF\xBB\xBF".implode(';', [...$definition['required'], ...$definition['optional']])."\r\n";
    }

    /** @return list<array{line: int, data: array<string, string>}> */
    public function parse(string $type, string $csv): array
    {
        $definition = $this->definition($type);
        if (strlen($csv) > 2 * 1024 * 1024 || !mb_check_encoding($csv, 'UTF-8') || str_contains($csv, "\0")) {
            throw new \InvalidArgumentException('Le CSV doit être en UTF-8 et ne pas dépasser 2 Mo.');
        }
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        $this->validateQuotes($csv);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);
        try {
            $headers = fgetcsv($stream, null, ';', '"', '');
            if (!$headers || in_array(null, $headers, true)) {
                throw new \InvalidArgumentException('Le CSV est vide.');
            }
            $headers = array_map('trim', $headers);
            if (count(array_unique($headers)) !== count($headers)) {
                throw new \InvalidArgumentException('Les noms de colonnes doivent être uniques.');
            }
            $missing = array_diff($definition['required'], $headers);
            $unknown = array_diff($headers, [...$definition['required'], ...$definition['optional']]);
            if ($missing || $unknown) {
                throw new \InvalidArgumentException('Colonnes manquantes : '.implode(', ', $missing).'. Colonnes inconnues : '.implode(', ', $unknown).'. Téléchargez le modèle.');
            }
            $rows = [];
            $line = 2;
            while (($values = fgetcsv($stream, null, ';', '"', '')) !== false) {
                $start = $line;
                // Physical line numbers, including quoted multiline cells.
                $line += max(1, 1 + array_sum(array_map(static fn ($value) => substr_count((string) $value, "\n"), $values)));
                if ($values === [null] || (count($values) === 1 && trim((string) $values[0]) === '')) {
                    continue;
                }
                if (count($values) !== count($headers)) {
                    throw new \InvalidArgumentException(sprintf('Ligne %d : nombre de cellules incorrect.', $start));
                }
                $data = array_fill_keys([...$definition['required'], ...$definition['optional']], '');
                $rows[] = ['line' => $start, 'data' => array_replace($data, array_combine($headers, array_map('trim', $values)))];
                if (count($rows) > 500) {
                    throw new \InvalidArgumentException('Un import est limité à 500 lignes.');
                }
            }
            if (!$rows) {
                throw new \InvalidArgumentException('Ajoutez au moins une ligne de données au CSV.');
            }
            return $rows;
        } finally {
            fclose($stream);
        }
    }

    private function validateQuotes(string $csv): void
    {
        $quoted = false;
        $start = true;
        $closed = false;
        for ($i = 0, $length = strlen($csv); $i < $length; ++$i) {
            $char = $csv[$i];
            if ($quoted) {
                if ($char === '"') {
                    if (($csv[$i + 1] ?? '') === '"') { ++$i; }
                    else { $quoted = false; $closed = true; }
                }
            } elseif ($char === ';' || $char === "\r" || $char === "\n") {
                $start = true;
                $closed = false;
            } elseif ($char === '"' && $start) {
                $quoted = true;
                $start = false;
            } elseif ($closed || $char === '"') {
                throw new \InvalidArgumentException('Guillemets CSV mal formés. Entourez toute la cellule de guillemets et doublez les guillemets intérieurs.');
            } else {
                $start = false;
            }
        }
        if ($quoted) {
            throw new \InvalidArgumentException('Guillemets CSV non fermés.');
        }
    }
}
