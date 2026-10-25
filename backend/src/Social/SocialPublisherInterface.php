<?php

namespace App\Social;

use App\Entity\PostTarget;
use App\Enum\Platform;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/** Un'unica operazione: pubblicare. Un adapter per piattaforma. */
#[AutoconfigureTag('app.social_publisher')]
interface SocialPublisherInterface
{
    public function platform(): Platform;

    /**
     * Pubblica il post e restituisce l'id esterno. Puo' valorizzare containerId sul post
     * per i passi intermedi (il chiamante persiste lo stato).
     *
     * @throws Exception\AuthRejectedException
     * @throws Exception\PermanentPublishException
     * @throws Exception\TransientPublishException
     * @throws Exception\PublishInProgressException
     */
    public function publish(PostTarget $post): string;
}
