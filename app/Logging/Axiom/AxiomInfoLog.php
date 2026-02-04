<?php

namespace App\Logging\Axiom;

/**
 * Info-level Axiom log. Same fixed schema as AxiomLogEvent with level=info.
 * No exception field.
 */
final class AxiomInfoLog
{
    public static function make(
        string $message,
        ?AxiomRequestPayload $req = null,
        ?AxiomResponsePayload $res = null,
        array|string $context = [],
        ?string $app = null,
        ?string $env = null,
    ): AxiomLogEvent {
        return new AxiomLogEvent(
            level: AxiomLogLevel::Info,
            message: $message,
            req: $req,
            res: $res,
            context: $context,
            exception: null,
            app: $app,
            env: $env,
        );
    }
}
