<?php

namespace App\Logging\Axiom;

/**
 * Error-level Axiom log. Same fixed schema as AxiomLogEvent with level=error.
 * Includes optional exception payload.
 */
final class AxiomErrorLog
{
    public static function make(
        string $message,
        ?AxiomExceptionPayload $exception = null,
        ?AxiomRequestPayload $req = null,
        ?AxiomResponsePayload $res = null,
        array|string $context = [],
        ?string $app = null,
        ?string $env = null,
    ): AxiomLogEvent {
        return new AxiomLogEvent(
            level: AxiomLogLevel::Error,
            message: $message,
            req: $req,
            res: $res,
            context: $context,
            exception: $exception,
            app: $app,
            env: $env,
        );
    }
}
