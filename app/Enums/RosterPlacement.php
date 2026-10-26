<?php

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum RosterPlacement: string
{
    case Standalone = 'standalone';
    case Group = 'group';
    case Event = 'event';
}
