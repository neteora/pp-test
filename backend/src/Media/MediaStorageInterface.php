<?php

namespace App\Media;

interface MediaStorageInterface
{
    /** Salva il file e restituisce l'URL pubblico HTTPS (necessario a Instagram/TikTok). */
    public function store(string $key, string $localPath, string $contentType): string;
}
