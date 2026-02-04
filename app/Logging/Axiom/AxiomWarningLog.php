<?php

namespace App\Logging\Axiom;

/**
 * Warning-level Axiom log. Same fixed schema as AxiomLogEvent with level=warning.
 * No exception field.
 */
final class AxiomWarningLog
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
            level: AxiomLogLevel::Warning,
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
