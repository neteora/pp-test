<?php

namespace App\Controller;

use App\Entity\MediaAsset;
use App\Media\MediaStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class MediaController extends ApiController
{
    private const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'video/mp4' => 'mp4'];
    private const MAX_BYTES = 50 * 1024 * 1024;

    public function __construct(private EntityManagerInterface $em, private MediaStorageInterface $storage) {}

    #[Route('/api/media', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw new BadRequestHttpException("File mancante (campo 'file')");
        }
        $mime = (string) $file->getMimeType();
        if (!isset(self::ALLOWED[$mime])) {
            throw new BadRequestHttpException('Tipo di file non consentito');
        }
        if ($file->getSize() > self::MAX_BYTES) {
            throw new BadRequestHttpException('File troppo grande');
        }

        $user = $this->me();
        $key = sprintf('users/%d/%s.%s', $user->getId(), bin2hex(random_bytes(16)), self::ALLOWED[$mime]);
        $url = $this->storage->store($key, $file->getPathname(), $mime);

        $asset = new MediaAsset($user, $key, $mime, (int) $file->getSize(), $url);
        $this->em->persist($asset);
        $this->em->flush();

        return new JsonResponse($asset->toArray(), Response::HTTP_CREATED);
    }
}
