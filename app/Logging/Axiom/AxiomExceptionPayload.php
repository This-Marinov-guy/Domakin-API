<?php

namespace App\Logging\Axiom;

/**
 * Fixed exception payload for error logs. Grouped under one field.
 */
final class AxiomExceptionPayload
{
    public function __construct(
        public string $message,
        public ?int $code = null,
        public ?string $file = null,
        public ?int $line = null,
    ) {
    }

    public function toArray(): array
    {
        return array_filter([
            'message' => $this->message,
            'code' => $this->code,
            'file' => $this->file,
            'line' => $this->line,
        ], fn ($v) => $v !== null);
    }
}
