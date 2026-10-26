<?php

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum GroupJoinMethod: string
{
    case Invite = 'invite';
    case Link = 'link';
}
