<?php

namespace App\Finance\Domain\Validation;

final class InvoicePaymentTerms
{
    /** @return list<string> */
    public static function normalize(mixed $paymentTerms): array
    {
        if (!is_array($paymentTerms) || !array_is_list($paymentTerms)) {
            throw new \InvalidArgumentException('paymentTerms doit être une liste de chaînes de caractères.');
        }

        $normalized = [];
        foreach ($paymentTerms as $term) {
            if (!is_string($term)) {
                throw new \InvalidArgumentException('paymentTerms doit être une liste de chaînes de caractères.');
            }
            $term = trim($term);
            if ($term !== '') {
                $normalized[] = $term;
            }
        }

        return $normalized;
    }
}
