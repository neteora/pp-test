<?php

namespace App\MessageHandler;

use App\Entity\GenerationRequest;
use App\Enum\GenerationStatus;
use App\Llm\Exception\LlmPermanentException;
use App\Llm\Exception\LlmTransientException;
use App\Llm\LlmClientInterface;
use App\Message\GenerateText;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GenerateTextHandler
{
    public function __construct(private EntityManagerInterface $em, private LlmClientInterface $llm) {}

    public function __invoke(GenerateText $message): void
    {
        $this->em->clear();
        $request = $this->em->find(GenerationRequest::class, $message->generationRequestId);
        if ($request === null || in_array($request->getStatus(), [GenerationStatus::Done, GenerationStatus::Failed], true)) {
            return;
        }

        $request->setStatus(GenerationStatus::Running);
        $this->em->flush();

        try {
            $request->complete($this->llm->generate($message->userText, $request->getParams()));
        } catch (LlmTransientException $e) {
            // Retry con backoff solo per errori temporanei
            $request->setStatus(GenerationStatus::Pending);
            $this->em->flush();
            throw $e;
        } catch (LlmPermanentException $e) {
            $request->fail($e->getMessage());
        }
        $this->em->flush();
    }
}
