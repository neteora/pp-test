<?php

namespace App\Message;

final readonly class PublishPost
{
    public function __construct(public int $postId) {}
}
