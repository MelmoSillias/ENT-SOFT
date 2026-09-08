<?php

namespace App\Upload\Application\Dto;

/** Résultat du rattachement d'un upload chunké à un module métier. */
final class ClaimedUpload
{
    public function __construct(
        public readonly string $publicPath,
        public readonly string $storedFilename,
        public readonly string $originalFilename,
        public readonly ?string $mimeType,
        public readonly int $byteSize,
    ) {
    }
}
