<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Households\Enums\HouseholdRole;
use PHPUnit\Framework\TestCase;

final class HouseholdRoleTest extends TestCase
{
    public function test_roles_are_explicit_and_stable_for_persistence(): void
    {
        self::assertSame(['owner', 'member'], array_column(HouseholdRole::cases(), 'value'));
    }
}
