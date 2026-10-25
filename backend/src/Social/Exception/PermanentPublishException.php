<?php

namespace App\Social\Exception;

/** Errore non recuperabile (contenuto o richiesta non accettati): niente retry. */
final class PermanentPublishException extends \RuntimeException {}
