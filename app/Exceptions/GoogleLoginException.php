<?php

namespace App\Exceptions;

use RuntimeException;

/** A Google sign-in that can't be accepted; the message is shown on the login page. */
class GoogleLoginException extends RuntimeException {}
