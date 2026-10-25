<?php

namespace App\Social\Exception;

/** La piattaforma sta ancora elaborando il media: si riprova al prossimo giro. */
final class PublishInProgressException extends \RuntimeException {}
