<?php

namespace App\Services\ProjectCreation;

use Exception;

/**
 * Carries a stable machine-readable error code (e.g. "NOT_READY",
 * "COMPANY_RULES_REQUIRED", "SESSION_NOT_CONFIRMABLE") through to the
 * controller layer so the JSON response can expose it verbatim.
 */
class ProjectCreationException extends Exception
{
    public function __construct(private readonly string $errorCode, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $errorCode);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
