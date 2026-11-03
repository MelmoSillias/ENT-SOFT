<?php

namespace App\DataImport\Application;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImportPackageReader
{
    public const MAX_FILE = 10 * 1024 * 1024;

    /** Reads bounded entries in memory; never extracts archive paths to disk. */
    public function read(UploadedFile $file): array
    {
        if (!$file->isValid() || $file->getSize() > 20 * 1024 * 1024) {
            throw new \InvalidArgumentException('Fichier invalide ou supérieur à 20 Mo.');
        }
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension === 'csv') {
            return ['csv' => file_get_contents($file->getPathname()), 'files' => []];
        }
        if ($extension !== 'zip') {
            throw new \InvalidArgumentException('Sélectionnez un CSV ou un ZIP contenant donnees.csv et pieces/.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($file->getPathname()) !== true) {
            throw new \InvalidArgumentException('Archive ZIP illisible.');
        }
        try {
            if ($zip->numFiles > 1000) {
                throw new \InvalidArgumentException('Le ZIP contient trop de fichiers (1000 maximum).');
            }
            $files = [];
            $names = [];
            $total = 0;
            $csv = null;
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $entry = $zip->statIndex($i);
                $name = $entry['name'];
                if (!self::safePath($name) || isset($names[$name])) {
                    throw new \InvalidArgumentException('Chemin ZIP invalide ou en double : '.$name);
                }
                $names[$name] = true;
                $total += $entry['size'];
                if ($entry['size'] > self::MAX_FILE || $total > 50 * 1024 * 1024) {
                    throw new \InvalidArgumentException('Limites du ZIP : 10 Mo par fichier, 50 Mo décompressés.');
                }
                if (str_ends_with($name, '/')) {
                    continue;
                }
                if ($name !== 'donnees.csv' && !str_starts_with($name, 'pieces/')) {
                    throw new \InvalidArgumentException('Le ZIP doit contenir donnees.csv à la racine et les justificatifs dans pieces/.');
                }
                $content = $zip->getFromIndex($i, self::MAX_FILE + 1);
                if ($content === false || strlen($content) !== $entry['size']) {
                    throw new \InvalidArgumentException('Fichier ZIP illisible ou chiffré : '.$name);
                }
                if ($name === 'donnees.csv') {
                    $csv = $content;
                } else {
                    self::validateAttachment($name, $content);
                    $files[$name] = $content;
                }
            }
            if ($csv === null) {
                throw new \InvalidArgumentException('Le fichier donnees.csv est absent du ZIP.');
            }
            return ['csv' => $csv, 'files' => $files];
        } finally {
            $zip->close();
        }
    }

    public static function safePath(string $path): bool
    {
        return $path !== '' && !str_starts_with($path, '/') && !str_contains($path, '\\')
            && !preg_match('/[\x00-\x1F:]/', $path)
            && !in_array('..', explode('/', $path), true) && !in_array('.', explode('/', $path), true);
    }

    public static function validateAttachment(string $name, string $content): void
    {
        $types = [
            'pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
            'png' => ['image/png'], 'webp' => ['image/webp'], 'txt' => ['text/plain'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        ];
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);
        if ($content === '' || !isset($types[$extension]) || !in_array($mime, $types[$extension], true) || mb_strlen(basename($name)) > 200) {
            throw new \InvalidArgumentException('Justificatif invalide : '.$name.'. Formats : PDF, JPG, PNG, WEBP, DOCX, XLSX, TXT.');
        }
    }
}
