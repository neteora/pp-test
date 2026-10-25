<?php

namespace App\Media;

/** Storage su disco (sviluppo): salva in public/uploads, servito direttamente da Apache. */
final class LocalMediaStorage implements MediaStorageInterface
{
    public function __construct(private string $uploadDir, private string $publicBaseUrl) {}

    public function store(string $key, string $localPath, string $contentType): string
    {
        $target = rtrim($this->uploadDir, '/').'/'.$key;
        $dir = dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cartella upload non scrivibile');
        }
        if (!copy($localPath, $target)) {
            throw new \RuntimeException('Salvataggio del file non riuscito');
        }

        return rtrim($this->publicBaseUrl, '/').'/'.$key;
    }
}
