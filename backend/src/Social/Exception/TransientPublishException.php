<?php

namespace App\Social\Exception;

/** Errore temporaneo (rete, 5xx, rate limit): retry con backoff. */
final class TransientPublishException extends \RuntimeException {}
