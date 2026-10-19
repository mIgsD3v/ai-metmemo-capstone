<?php

namespace App\Services\Ai;

use RuntimeException;

/** The AI service could not complete a request: offline, not configured, or the model call failed. */
class AiServiceException extends RuntimeException {}
