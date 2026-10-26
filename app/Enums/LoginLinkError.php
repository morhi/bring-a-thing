<?php

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum LoginLinkError: string
{
    case Expired = 'expired';
    case Invalid = 'invalid';
}
