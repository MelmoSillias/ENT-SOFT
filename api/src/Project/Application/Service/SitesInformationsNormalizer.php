<?php

namespace App\Project\Application\Service;

/**
 * Sanitizes project sitesInformations definitions and per-site informationsValues.
 */
final class SitesInformationsNormalizer
{
    private const MAX_LABEL_LENGTH = 80;
    private const MAX_VALUE_LENGTH = 2000;
    private const MAX_KEY_LENGTH = 64;

    /**
     * @param list<mixed> $items
     *
     * @return list<array{key: string, label: string}>
     */
    public static function normalizeDefinitions(array $items): array
    {
        $result = [];
        $usedKeys = [];
        $usedLabels = [];

        foreach ($items as $item) {
            if (!\is_array($item)) {
                continue;
            }

            $label = self::sanitizePlainText((string) ($item['label'] ?? ''), self::MAX_LABEL_LENGTH);
            if ('' === $label) {
                continue;
            }

            $labelKey = mb_strtolower($label);
            if (isset($usedLabels[$labelKey])) {
                continue;
            }
            $usedLabels[$labelKey] = true;

            $rawKey = isset($item['key']) ? (string) $item['key'] : '';
            $key = self::sanitizeKey($rawKey !== '' ? $rawKey : self::slugify($label));
            $base = $key;
            $n = 2;
            while (isset($usedKeys[$key])) {
                $key = substr($base, 0, self::MAX_KEY_LENGTH - 3).'_'.$n++;
            }
            $usedKeys[$key] = true;

            $result[] = ['key' => $key, 'label' => $label];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    public static function normalizeValues(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            $safeKey = self::sanitizeKey((string) $key);
            if ('' === $safeKey) {
                continue;
            }
            if (null === $value) {
                $result[$safeKey] = null;
                continue;
            }
            if (\is_bool($value) || \is_int($value) || \is_float($value)) {
                $result[$safeKey] = $value;
                continue;
            }
            $result[$safeKey] = self::sanitizePlainText((string) $value, self::MAX_VALUE_LENGTH);
        }

        return $result;
    }

    public static function sanitizePlainText(string $value, int $maxLength): string
    {
        $stripped = strip_tags($value);
        $stripped = preg_replace('/[\x00-\x1F\x7F]/u', '', $stripped) ?? '';
        $trimmed = trim($stripped);
        if ('' === $trimmed) {
            return '';
        }

        return mb_substr($trimmed, 0, $maxLength);
    }

    public static function sanitizeKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9_]+/', '_', $key) ?? '';
        $key = trim($key, '_');

        return mb_substr($key, 0, self::MAX_KEY_LENGTH);
    }

    public static function slugify(string $label): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label);
        if (false === $ascii || '' === $ascii) {
            $ascii = $label;
        }
        $slug = strtolower(trim($ascii));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug) ?? '';
        $slug = trim($slug, '_');

        return '' !== $slug ? $slug : 'info';
    }
}
