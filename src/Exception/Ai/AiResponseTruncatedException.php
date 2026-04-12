<?php

namespace App\Exception\Ai;

/**
 * Thrown when the AI provider indicates that the response was truncated (e.g., finish_reason: "length").
 */
class AiResponseTruncatedException extends \RuntimeException
{
    public function __construct(
        string $message = "AI response was truncated due to length limits",
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
