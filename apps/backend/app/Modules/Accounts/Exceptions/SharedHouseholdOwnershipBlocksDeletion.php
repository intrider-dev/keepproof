<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Exceptions;

use RuntimeException;

final class SharedHouseholdOwnershipBlocksDeletion extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'Transfer ownership or remove the other members from every owned household before deleting your account.',
        );
    }
}
