<?php

namespace App\Enums;

enum ParticipationStatus: string
{
    case Accepted = 'accepted';
    case Pending = 'pending';
    case Declined = 'declined';
}
