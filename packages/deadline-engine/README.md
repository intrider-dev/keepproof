# KeepProof Deadline Engine

A small, framework-agnostic PHP library for calculating explainable purchase return and warranty deadlines.

The package deliberately does not contain country-specific consumer-law rules. The application supplies the applicable period and counting convention, and the engine applies deterministic calendar arithmetic. This keeps legal or merchant policy data outside a date-calculation primitive and makes every result reviewable before it becomes active.

## Requirements

- PHP 8.5 or later
- Composer 2
- An up-to-date IANA timezone database in PHP

There are no runtime dependencies outside PHP.

## Installation

As a monorepo path dependency:

```json
{
  "repositories": [
    { "type": "path", "url": "../../packages/deadline-engine" }
  ],
  "require": {
    "keepproof/deadline-engine": "@dev"
  }
}
```

The package is structured for independent publication, but KeepProof does not claim that it is currently available on Packagist.

## Quick start

```php
<?php

declare(strict_types=1);

use KeepProof\DeadlineEngine\CountingConvention;
use KeepProof\DeadlineEngine\DeadlineCalculator;
use KeepProof\DeadlineEngine\EndOfMonthPolicy;
use KeepProof\DeadlineEngine\Value\DeadlineTerms;
use KeepProof\DeadlineEngine\Value\LocalDateTime;
use KeepProof\DeadlineEngine\Value\Period;
use KeepProof\DeadlineEngine\Value\PurchaseDeadlineRequest;

$request = new PurchaseDeadlineRequest(
    purchasedAt: LocalDateTime::fromLocal(
        '2026-01-31 15:30:00',
        'America/New_York',
    ),
    returnTerms: new DeadlineTerms(
        period: Period::days(30),
        countingConvention: CountingConvention::PurchaseDayExclusive,
    ),
    warrantyTerms: new DeadlineTerms(
        period: Period::years(2),
        countingConvention: CountingConvention::PurchaseDayExclusive,
        endOfMonthPolicy: EndOfMonthPolicy::Clamp,
    ),
);

$result = (new DeadlineCalculator())->calculate($request);

echo $result->returnDeadline?->effectiveDeadline->toRfc3339();
// 2026-03-02T15:30:00.000000-05:00

echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
```

`returnTerms` and `warrantyTerms` are independent and either may be `null`. At least one must be supplied.

## Counting conventions

The enum names describe whether the **purchase calendar day** counts toward the period:

- `PurchaseDayExclusive`: adds the complete period. January 1 plus 30 calendar days ends January 31 at the same local wall time.
- `PurchaseDayInclusive`: the purchase date is day one. January 1 with a 30-day period ends January 30. For month and year periods, the engine calculates the exclusive anniversary boundary and uses the preceding local calendar date.

This distinction is explicit because merchant policies and applicable rules vary. A caller must not infer a convention from a country name.

## Month-end behavior

PHP's native month addition can overflow an unavailable day into the following month. The engine never uses that behavior:

- `Clamp`: keep the ordinal day when possible, otherwise cap it at the last day of the target month. `2024-01-31 + 1 month` becomes `2024-02-29`.
- `PreserveEndOfMonth`: when the purchase is the final day of its month, use the final day of the target month. `2024-02-29 + 1 month` becomes `2024-03-31`. Other unavailable ordinal days are clamped.

Leap-year rules include the Gregorian century exceptions: 2100 is not a leap year and 2400 is.

## Timezones and DST

Deadlines use calendar units in the purchase's IANA timezone, not fixed durations in seconds. Consequently, a calendar day crossing a daylight-saving transition may represent 23 or 25 elapsed hours while retaining the same wall-clock time.

```php
$purchase = LocalDateTime::fromLocal(
    '2024-03-09 12:00:00',
    'America/New_York',
);

// One exclusive calendar day later is 2024-03-10 12:00:00 -04:00,
// which is 23 elapsed hours after the purchase instant.
```

Safety rules for local times are intentional:

- `fromLocal()` rejects nonexistent times in a spring-forward gap.
- `fromLocal()` rejects ambiguous times in a fall-back overlap instead of guessing an offset.
- To resolve an overlap explicitly, create an offset-qualified instant, convert it to the intended IANA timezone, and call `fromDateTime()`.
- If a *calculated* target wall time falls in a DST gap, it moves forward by the transition gap and the trace contains `dst_gap_resolved`.
- Fixed-offset zones such as `+03:00` are rejected because they cannot represent future timezone rule changes. `UTC` is accepted.

```php
use DateTimeImmutable;
use DateTimeZone;

$resolvedOverlap = (new DateTimeImmutable('2024-11-03T01:30:00-04:00'))
    ->setTimezone(new DateTimeZone('America/New_York'));

$purchase = LocalDateTime::fromDateTime($resolvedOverlap);
```

## Manual overrides

An override requires a human-readable reason. The effective deadline changes, while the calculated baseline remains in the result for audit and explanation.

```php
use KeepProof\DeadlineEngine\Value\ManualOverride;

$terms = new DeadlineTerms(
    period: Period::days(30),
    manualOverride: new ManualOverride(
        deadline: LocalDateTime::fromLocal(
            '2026-03-15 18:00:00',
            'America/New_York',
        ),
        reason: 'Merchant granted a written extension.',
    ),
);
```

Overrides earlier than the purchase instant are rejected. An override submitted in another IANA timezone is represented in the purchase timezone without changing its instant.

## Explainable results

Each `DeadlineResult` contains:

- `effectiveDeadline`: the date used by the application;
- `calculatedDeadline`: the deterministic baseline, even when overridden;
- `source`: `calculated` or `manual_override`;
- `ruleSummary`: a compact readable summary;
- `trace`: structured steps with stable rule codes and context.

Typical trace codes include `period_applied`, `purchase_day_excluded`, `end_of_month_resolved`, `timezone_offset_changed`, and `manual_override_applied`. The value objects implement `JsonSerializable`, so the structure can cross an API boundary without exposing framework types.

## Validation and exceptions

All expected input failures implement `KeepProof\DeadlineEngine\Exception\DeadlineEngineException`. More specific exceptions include:

- `InvalidLocalDateTime`;
- `InvalidTimezone`;
- `InvalidPeriod`;
- `InvalidManualOverride`;
- `InvalidDeadlineRequest`;
- `DateOutOfRange`.

Periods are positive and bounded to prevent pathological date arithmetic: 366,000 days, 12,000 months, or 1,000 years. Calculated years must stay within 0001–9999.

## Development

From this directory:

```bash
composer install
composer test
composer analyse
composer security
composer check
```

`composer check` validates Composer metadata strictly, runs PHPStan at level `max`, executes PHPUnit, and audits the locked dependency graph. The test suite uses table-driven examples plus generated boundary matrices covering month ends, leap years, Gregorian century rules, DST transitions, overrides, invalid input, and JSON explanations.

Code coverage requires a local PCOV or Xdebug driver and is intentionally not faked when neither extension is present:

```bash
vendor/bin/phpunit --coverage-text
```

## Scope and trade-offs

- The engine calculates dates; it does not decide which law, merchant policy, or warranty term applies.
- It preserves the purchase wall-clock time rather than forcing end-of-day. The application may collect a date with an explicit business cutoff if that is what a merchant promises.
- Gregorian calendar arithmetic and IANA timezone behavior come from explicit package code plus PHP's maintained timezone database; Carbon and framework helpers are not required.
- Reminder scheduling, holiday calendars, business-day adjustments, persistence, and notification delivery belong to the host application.
- A calculated deadline is an aid, not legal advice, and should be confirmed by the user against their purchase terms.

## License

MIT. See [LICENSE](LICENSE).
