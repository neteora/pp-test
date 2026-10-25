<?php

namespace App\Enum;

enum AccountStatus: string
{
    case Active = 'active';
    case NeedsReauth = 'needs_reauth';
}
