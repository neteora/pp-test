<?php

namespace App\Controller;

use App\Entity\GenerationRequest;
use App\Llm\LlmClientInterface;
use App\Message\GenerateText;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/** Generazione asincrona: POST risponde 202, il client fa polling su GET. Il testo e' sempre una bozza. */
final class GenerationController extends ApiController
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private LlmClientInterface $llm,
    ) {}

    #[Route('/api/generations', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $b = $this->body($request);
        $text = $this->requireString($b, 'text', 20000);

        $params = $this->llm->defaultParams();
        if (isset($b['maxTokens'])) {
            $params['max_tokens'] = max(1, min(4096, (int) $b['maxTokens']));
        }

        $gen = new GenerationRequest($this->me(), $this->llm->model(), $params);
        $this->em->persist($gen);
        $this->em->flush();

        $this->bus->dispatch(new GenerateText($gen->getId(), $text));

        return new JsonResponse($gen->toArray(), Response::HTTP_ACCEPTED);
    }

    #[Route('/api/generations/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $gen = $this->em->find(GenerationRequest::class, $id) ?? throw new NotFoundHttpException();

        return new JsonResponse($gen->toArray());
    }
}
