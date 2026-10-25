<?php

namespace App\EventSubscriber;

use App\Entity\GenerationRequest;
use App\Message\GenerateText;
use App\Message\PublishPost;
use App\Service\PostTransitions;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/** Quando i tentativi finiscono (messaggio verso il transport "failed") lo stato diventa failed. */
final class MessengerFailureSubscriber implements EventSubscriberInterface
{
    public function __construct(private PostTransitions $posts, private EntityManagerInterface $em) {}

    public static function getSubscribedEvents(): array
    {
        return [WorkerMessageFailedEvent::class => 'onFailed'];
    }

    public function onFailed(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }
        $message = $event->getEnvelope()->getMessage();
        // Solo il nome della classe: niente risposte delle piattaforme nei log/DB
        $reason = 'Tentativi esauriti ('.(new \ReflectionClass($event->getThrowable()))->getShortName().')';

        if ($message instanceof PublishPost) {
            $this->posts->markFailed($message->postId, $reason);
        } elseif ($message instanceof GenerateText) {
            $this->em->clear();
            $request = $this->em->find(GenerationRequest::class, $message->generationRequestId);
            if ($request !== null) {
                $request->fail($reason);
                $this->em->flush();
            }
        }
    }
}
