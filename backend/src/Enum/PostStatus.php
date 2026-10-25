<?php

namespace App\Enum;

/**
 * draft -> pending (solo per azione dell'utente autenticato, mai da un job)
 *       -> queued (Scheduler) -> publishing (worker) -> published | failed
 */
enum PostStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Queued = 'queued';
    case Publishing = 'publishing';
    case Published = 'published';
    case Failed = 'failed';
}
