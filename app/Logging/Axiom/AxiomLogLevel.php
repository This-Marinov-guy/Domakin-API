<?php

namespace App\Logging\Axiom;

enum AxiomLogLevel: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
}
