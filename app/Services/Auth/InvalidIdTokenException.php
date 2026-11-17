<?php

namespace App\Services\Auth;

use RuntimeException;

/**
 * Raised when an ID token is malformed, expired, wrongly signed or was
 * issued by another provider or for another client.
 */
class InvalidIdTokenException extends RuntimeException {}
