<?php

namespace App\DataImport\Application;

use App\Finance\Application\Command\CreateInvoice\CreateInvoiceCommand;
use App\Finance\Application\Service\InvoiceVatValidator;
use App\Finance\Domain\Enum\InvoiceStatus;
use App\Finance\Domain\Validation\InvoicePaymentTerms;
use App\SharedKernel\Domain\Validation\FieldValidator;

final class InvoiceImportPreparer
{
    public function prepare(array $row, ?string $clientId): CreateInvoiceCommand
    {
        if ($clientId === null) {
            throw new \InvalidArgumentException('client_code : un client existant et actif est requis.');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $row['date']);
        if (!$date || $date->format('Y-m-d') !== $row['date']) {
            throw new \InvalidArgumentException('Date invalide : utilisez AAAA-MM-JJ.');
        }
        $status = ($row['statut'] ?? '') !== '' ? $row['statut'] : 'draft';
        if (!InvoiceStatus::tryFrom($status)) {
            throw new \InvalidArgumentException('statut : draft, quote ou invoiced.');
        }
        $enabled = match ($row['tva_active'] ?? '') {
            '', 'false', '0' => false,
            'true', '1' => true,
            default => throw new \InvalidArgumentException('tva_active : true, false, 1 ou 0.'),
        };
        $rate = ($row['taux_tva'] ?? '') !== '' ? $this->number($row['taux_tva'], 'taux_tva') : 18.0;
        InvoiceVatValidator::validate($enabled, $rate);
        $terms = ($row['conditions_paiement'] ?? '') !== ''
            ? InvoicePaymentTerms::normalize($this->json($row['conditions_paiement'], 'conditions_paiement'))
            : [];
        $input = $this->json($row['lignes'], 'lignes');
        if (!is_array($input) || !array_is_list($input) || !$input) {
            throw new \InvalidArgumentException('lignes : une liste JSON non vide est requise.');
        }
        $lines = [];
        $subtotal = 0.0;
        foreach ($input as $index => $line) {
            $field = 'lignes['.$index.']';
            if (!$line instanceof \stdClass || array_diff(array_keys(get_object_vars($line)), ['description', 'quantity', 'unitPrice', 'unit'])) {
                throw new \InvalidArgumentException($field.' : objet attendu avec description, quantity, unitPrice et éventuellement unit.');
            }
            if (!is_string($line->description ?? null) || mb_strlen($line->description) > 255) {
                throw new \InvalidArgumentException($field.'.description : chaîne de 1 à 255 caractères requise.');
            }
            $description = FieldValidator::requireNonEmpty($line->description, $field.'.description');
            $quantity = $this->number($line->quantity ?? null, $field.'.quantity');
            $price = $this->number($line->unitPrice ?? null, $field.'.unitPrice');
            if ($quantity <= 0) {
                throw new \InvalidArgumentException($field.'.quantity : doit être supérieure à 0.');
            }
            $unit = $line->unit ?? 'Lot';
            if (!is_string($unit) || mb_strlen($unit) > 50) {
                throw new \InvalidArgumentException($field.'.unit : chaîne de 50 caractères maximum.');
            }
            // Match InvoiceLine's per-line rounding; reject overflow before any persistence.
            $subtotal += round($quantity * $price, 2);
            if (!is_finite($subtotal) || !is_finite($subtotal + ($enabled ? round($subtotal * $rate / 100, 2) : 0))) {
                throw new \InvalidArgumentException($field.' : montant trop grand.');
            }
            $unit = trim($unit);
            $lines[] = ['description' => $description, 'quantity' => $quantity, 'unitPrice' => $price, 'unit' => $unit !== '' ? $unit : 'Lot'];
        }
        return new CreateInvoiceCommand(
            date: $row['date'], clientId: $clientId, status: $status,
            projectLabel: ($row['projet_libelle'] ?? '') !== '' ? $row['projet_libelle'] : null,
            lines: $lines, vatEnabled: $enabled, vatRate: $rate, paymentTerms: $terms,
        );
    }

    private function json(string $value, string $field): mixed
    {
        try {
            return json_decode($value, false, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException($field.' : JSON invalide.', previous: $e);
        }
    }

    private function number(mixed $value, string $field): float
    {
        if ((!is_string($value) && !is_int($value) && !is_float($value))
            || !preg_match('/^-?\d+(?:[.,]\d+)?$/D', (string) $value)) {
            throw new \InvalidArgumentException($field.' : nombre attendu, sans séparateur de milliers.');
        }
        $number = (float) str_replace(',', '.', (string) $value);
        if (!is_finite($number)) {
            throw new \InvalidArgumentException($field.' : valeur trop grande.');
        }
        return $number;
    }
}
