<?php

namespace App\Message;

/** @param array<int, array{0:int,1:string}> $posts coppie [postId, platform] */
final readonly class ClaimDueChunk
{
    public function __construct(public array $posts) {}
}
