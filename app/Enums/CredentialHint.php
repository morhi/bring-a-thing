<?php

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum CredentialHint: string
{
    case Email = 'email';
    case NoEmail = 'no_email';
}
