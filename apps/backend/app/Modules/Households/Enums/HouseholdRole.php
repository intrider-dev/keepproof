<?php

declare(strict_types=1);

namespace App\Modules\Households\Enums;

enum HouseholdRole: string
{
    case Owner = 'owner';
    case Member = 'member';
}
