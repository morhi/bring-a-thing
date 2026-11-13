<?php

namespace App\Enums;

enum DashboardPollStatus: string
{
    case Answered = 'answered';
    case Partial = 'partial';
    case Unanswered = 'unanswered';
}
