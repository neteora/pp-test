<?php

namespace App\MessageHandler;

use App\Entity\PostTarget;
use App\Enum\PostStatus;
use App\Message\PublishPost;
use App\Service\PostTransitions;
use App\Social\Exception\AuthRejectedException;
use App\Social\Exception\PermanentPublishException;
use App\Social\Exception\PublishInProgressException;
use App\Social\Exception\TransientPublishException;
use App\Social\PublisherRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PublishPostHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private PostTransitions $transitions,
        private PublisherRegistry $publishers,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(PublishPost $message): void
    {
        // queued -> publishing: vince un solo worker, gli altri (duplicati/retry tardivi) escono
        if (!$this->transitions->transition($message->postId, PostStatus::Queued, PostStatus::Publishing)) {
            return;
        }

        $this->em->clear();
        $post = $this->em->find(PostTarget::class, $message->postId);
        if ($post === null) {
            return;
        }

        // Idempotenza: se esiste gia' un id esterno il post e' gia' online
        if ($post->getExternalId() !== null) {
            $post->setStatus(PostStatus::Published);
            $this->em->flush();

            return;
        }

        $post->incrementAttempts();

        try {
            $externalId = $this->publishers->for($post->getSocialAccount()->getPlatform())->publish($post);
            $post->setExternalId($externalId);
            $post->setStatus(PostStatus::Published);
            $post->setLastError(null);
            $this->em->flush();
        } catch (PublishInProgressException $e) {
            // Non e' un errore: si riprova al prossimo giro (retry_strategy)
            $post->setStatus(PostStatus::Queued);
            $this->em->flush();
            throw $e;
        } catch (TransientPublishException $e) {
            $post->setStatus(PostStatus::Queued);
            $post->setLastError($e->getMessage());
            $this->em->flush();
            throw $e;
        } catch (AuthRejectedException $e) {
            $post->getSocialAccount()->markNeedsReauth();
            $post->setStatus(PostStatus::Failed);
            $post->setLastError($e->getMessage());
            $this->em->flush();
            $this->logger->warning('Token rifiutato', ['post' => $post->getId()]);
        } catch (PermanentPublishException $e) {
            $post->setStatus(PostStatus::Failed);
            $post->setLastError($e->getMessage());
            $this->em->flush();
        } catch (\Throwable $e) {
            // Esito incerto: torna in coda; il container gia' creato (se c'e') evita di duplicare
            $post->setStatus(PostStatus::Queued);
            $post->setLastError('Errore imprevisto: '.(new \ReflectionClass($e))->getShortName());
            $this->em->flush();
            throw $e;
        }
    }
}
