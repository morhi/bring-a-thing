<?php

namespace App\Enums;

enum PollResponseStatus: string
{
    case Yes = 'yes';
    case No = 'no';
    case Maybe = 'maybe';
}
