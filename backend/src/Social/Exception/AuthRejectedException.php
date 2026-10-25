<?php

namespace App\Social\Exception;

/** Il token e' stato rifiutato dalla piattaforma: l'account va riautenticato dall'utente. */
final class AuthRejectedException extends \RuntimeException {}
