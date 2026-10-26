<?php

namespace App\Enums;

enum DashboardWaitingKind: string
{
    case Poll = 'poll';
    case Event = 'event';
}
