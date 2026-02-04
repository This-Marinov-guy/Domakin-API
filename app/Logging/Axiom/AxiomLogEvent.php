<?php

namespace App\Logging\Axiom;

/**
 * Fixed Axiom log event. All variable data is grouped into object/string fields
 * so top-level field count stays under Axiom's limit.
 *
 * Top-level fields (max ~15): _time, level, message, app, env, req, res, context, exception
 * - req, res: objects with headers/body as JSON strings (no nested expansion)
 * - context: single JSON string (arbitrary key-value)
 * - exception: object (only for error level)
 */
final class AxiomLogEvent
{
    private const MAX_STRING_LENGTH = 32_000;
    private const MAX_CONTEXT_KEYS = 20;

    public function __construct(
        public AxiomLogLevel $level,
        public string $message,
        public ?AxiomRequestPayload $req = null,
        public ?AxiomResponsePayload $res = null,
        /** @var array<string, mixed>|string Context as array (will be JSON-encoded) or already JSON string */
        public array|string $context = [],
        public ?AxiomExceptionPayload $exception = null,
        public ?string $app = null,
        public ?string $env = null,
        public ?string $_time = null,
    ) {
        $this->app = $app ?? config('app.name');
        $this->env = $env ?? config('app.env');
        $this->_time = $_time ?? now()->toIso8601String();
    }

    /**
     * Build payload for Axiom ingest. Fixed field set only.
     *
     * @return array<string, mixed>
     */
    public function toAxiomPayload(): array
    {
        $payload = [
            '_time' => $this->_time,
            'level' => $this->level->value,
            'message' => $this->truncate($this->message, self::MAX_STRING_LENGTH),
            'app' => $this->app,
            'env' => $this->env,
        ];

        if ($this->req !== null) {
            $payload['req'] = $this->req->toArray();
        }
        if ($this->res !== null) {
            $payload['res'] = $this->res->toArray();
        }

        $payload['context'] = $this->normalizeContext($this->context);

        if ($this->exception !== null) {
            $payload['exception'] = $this->exception->toArray();
        }

        return $payload;
    }

    private function normalizeContext(array|string $context): string
    {
        if (is_string($context)) {
            return $this->truncate($context, self::MAX_STRING_LENGTH);
        }
        $limited = array_slice($context, 0, self::MAX_CONTEXT_KEYS, true);
        $encoded = json_encode($this->truncateValues($limited), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        return $this->truncate($encoded, self::MAX_STRING_LENGTH);
    }

    private function truncate(string $s, int $max): string
    {
        if (strlen($s) <= $max) {
            return $s;
        }
        return substr($s, 0, $max) . '...[truncated]';
    }

    /**
     * @param array<string, mixed> $arr
     * @return array<string, mixed>
     */
    private function truncateValues(array $arr): array
    {
        $out = [];
        foreach ($arr as $k => $v) {
            if (is_string($v)) {
                $out[$k] = $this->truncate($v, 2000);
            } elseif (is_array($v)) {
                $out[$k] = $this->truncateValues(array_slice($v, 0, 10));
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}
