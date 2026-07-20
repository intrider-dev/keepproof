<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Data;

final readonly class RegisterAccountData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $timezone,
        public string $locale,
    ) {}
}
