<?php

namespace App\Controller;

use App\Entity\GenerationRequest;
use App\Entity\MediaAsset;
use App\Entity\PostTarget;
use App\Entity\SocialAccount;
use App\Enum\AccountStatus;
use App\Enum\PostStatus;
use App\Http\Dt;
use App\Service\PostTransitions;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Tutte le letture passano dal filtro "user_owned": un id di un altro utente da' 404.
 * Il passaggio draft -> pending avviene SOLO qui (azione autenticata dell'utente).
 */
final class PostController extends ApiController
{
    public function __construct(private EntityManagerInterface $em, private PostTransitions $transitions) {}

    #[Route('/api/posts', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $criteria = [];
        if ($status = $request->query->get('status')) {
            $criteria['status'] = PostStatus::tryFrom((string) $status) ?? throw new BadRequestHttpException('Stato non valido');
        }
        $limit = max(1, min(100, $request->query->getInt('limit', 50)));
        $offset = max(0, $request->query->getInt('offset', 0));

        $posts = $this->em->getRepository(PostTarget::class)
            ->findBy($criteria, ['scheduledAt' => 'DESC', 'id' => 'DESC'], $limit, $offset);

        return new JsonResponse(['items' => array_map(static fn (PostTarget $p) => $p->toArray(), $posts)]);
    }

    #[Route('/api/posts/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        return new JsonResponse($this->find($id)->toArray());
    }

    #[Route('/api/posts', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $b = $this->body($request);
        $account = $this->em->find(SocialAccount::class, (int) ($b['socialAccountId'] ?? 0))
            ?? throw new BadRequestHttpException('Account social non valido');

        $post = new PostTarget($this->me(), $account, $this->requireString($b, 'text', 20000));
        $this->apply($post, $b);
        $this->em->persist($post);
        $this->em->flush();

        return new JsonResponse($post->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/api/posts/{id}', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $post = $this->find($id);
        if (!in_array($post->getStatus(), [PostStatus::Draft, PostStatus::Failed], true)) {
            throw new ConflictHttpException('Il post non e\' modificabile in questo stato');
        }
        $b = $this->body($request);
        if (array_key_exists('text', $b)) {
            $post->setText($this->requireString($b, 'text', 20000));
        }
        if (isset($b['socialAccountId'])) {
            $post->setSocialAccount($this->em->find(SocialAccount::class, (int) $b['socialAccountId'])
                ?? throw new BadRequestHttpException('Account social non valido'));
        }
        $this->apply($post, $b);
        // Un post fallito, una volta modificato, torna bozza e va riapprovato
        if ($post->getStatus() === PostStatus::Failed) {
            $post->setStatus(PostStatus::Draft);
            $post->setLastError(null);
            $post->setContainerId(null);
        }
        $this->em->flush();

        return new JsonResponse($post->toArray());
    }

    #[Route('/api/posts/{id}', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $post = $this->find($id);
        if (!in_array($post->getStatus(), [PostStatus::Draft, PostStatus::Failed], true)) {
            throw new ConflictHttpException('Solo bozze o post falliti possono essere eliminati');
        }
        $this->em->remove($post);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** Approvazione umana: draft -> pending. */
    #[Route('/api/posts/{id}/approve', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function approve(int $id): JsonResponse
    {
        $post = $this->find($id);
        if ($post->getScheduledAt() === null || $post->getScheduledAt() <= new \DateTimeImmutable()) {
            throw new BadRequestHttpException('Imposta una data di pubblicazione nel futuro');
        }
        if ($post->getSocialAccount()->getStatus() !== AccountStatus::Active) {
            throw new ConflictHttpException('Account da riautenticare: inserisci un nuovo token');
        }
        $this->move($post, PostStatus::Draft, PostStatus::Pending);

        return new JsonResponse($post->toArray());
    }

    /** Annulla la programmazione: pending -> draft (fallisce se lo Scheduler l'ha gia' preso). */
    #[Route('/api/posts/{id}/unschedule', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function unschedule(int $id): JsonResponse
    {
        $post = $this->find($id);
        $this->move($post, PostStatus::Pending, PostStatus::Draft);

        return new JsonResponse($post->toArray());
    }

    /** Riprova manuale di un post fallito: failed -> pending (rientra nel ciclo normale). */
    #[Route('/api/posts/{id}/retry', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function retry(int $id): JsonResponse
    {
        $post = $this->find($id);
        if ($post->getSocialAccount()->getStatus() !== AccountStatus::Active) {
            throw new ConflictHttpException('Account da riautenticare: inserisci un nuovo token');
        }
        // scheduled_at invariato: se e' nel passato parte al prossimo tick dello Scheduler
        $this->move($post, PostStatus::Failed, PostStatus::Pending, ['last_error' => null, 'attempts' => 0]);

        return new JsonResponse($post->toArray());
    }

    private function find(int $id): PostTarget
    {
        return $this->em->find(PostTarget::class, $id) ?? throw new NotFoundHttpException('Post non trovato');
    }

    private function move(PostTarget $post, PostStatus $from, PostStatus $to, array $set = []): void
    {
        if (!$this->transitions->transition($post->getId(), $from, $to, $this->me()->getId(), set: $set)) {
            throw new ConflictHttpException('Stato del post cambiato nel frattempo');
        }
        $this->em->refresh($post);
    }

    /** Campi opzionali comuni a create/update. */
    private function apply(PostTarget $post, array $b): void
    {
        if (array_key_exists('mediaAssetId', $b)) {
            $post->setMediaAsset($b['mediaAssetId'] === null ? null
                : ($this->em->find(MediaAsset::class, (int) $b['mediaAssetId']) ?? throw new BadRequestHttpException('Media non valido')));
        }
        if (array_key_exists('generationRequestId', $b) && $b['generationRequestId'] !== null) {
            $post->setGenerationRequest($this->em->find(GenerationRequest::class, (int) $b['generationRequestId'])
                ?? throw new BadRequestHttpException('Generazione non valida'));
        }
        if (array_key_exists('scheduledAt', $b)) {
            try {
                $post->setScheduledAt($b['scheduledAt'] === null ? null
                    : Dt::parseToUtc((string) $b['scheduledAt'], $this->me()->getTimezone()));
            } catch (\InvalidArgumentException $e) {
                throw new BadRequestHttpException($e->getMessage());
            }
        }
    }
}
