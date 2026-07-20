<?php

declare(strict_types=1);

namespace KeepProof\DeadlineEngine\Value;

use DateTimeImmutable;
use DateTimeZone;
use JsonSerializable;
use KeepProof\DeadlineEngine\Exception\InvalidLocalDateTime;
use KeepProof\DeadlineEngine\Exception\InvalidTimezone;

/**
 * A resolved instant represented in an IANA timezone.
 *
 * Ambiguous local wall times are rejected by fromLocal(); callers that know the
 * intended UTC offset can create an offset-qualified DateTimeImmutable, convert
 * it to an IANA timezone, and use fromDateTime().
 *
 */
final readonly class LocalDateTime implements JsonSerializable
{
    private const LOCAL_FORMAT = 'Y-m-d H:i:s.u';

    private function __construct(private DateTimeImmutable $value)
    {
    }

    public static function fromLocal(string $localDateTime, string $ianaTimezone): self
    {
        $timezone = self::ianaTimezone($ianaTimezone);
        $canonical = self::canonicalLocalValue($localDateTime);
        $parsed = DateTimeImmutable::createFromFormat('!'.self::LOCAL_FORMAT, $canonical, $timezone);
        $errors = DateTimeImmutable::getLastErrors();

        if ($parsed === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidLocalDateTime(sprintf('"%s" is not a valid local date and time.', $localDateTime));
        }

        if ($parsed->format(self::LOCAL_FORMAT) !== $canonical) {
            throw new InvalidLocalDateTime(sprintf(
                '"%s" does not exist in timezone %s (it falls in a timezone transition gap).',
                $localDateTime,
                $ianaTimezone,
            ));
        }

        self::assertSupportedYear((int) $parsed->format('Y'));

        if (self::matchingInstantCount($canonical, $timezone) > 1) {
            throw new InvalidLocalDateTime(sprintf(
                '"%s" is ambiguous in timezone %s; provide an offset-qualified instant via fromDateTime().',
                $localDateTime,
                $ianaTimezone,
            ));
        }

        return new self($parsed);
    }

    public static function fromDateTime(DateTimeImmutable $dateTime): self
    {
        self::ianaTimezone($dateTime->getTimezone()->getName());
        self::assertSupportedYear((int) $dateTime->format('Y'));

        return new self($dateTime);
    }

    public function dateTime(): DateTimeImmutable
    {
        return $this->value;
    }

    public function timezone(): string
    {
        return $this->value->getTimezone()->getName();
    }

    public function inTimezone(string $ianaTimezone): self
    {
        return new self($this->value->setTimezone(self::ianaTimezone($ianaTimezone)));
    }

    public function isBefore(self $other): bool
    {
        return $this->value < $other->value;
    }

    public function toRfc3339(): string
    {
        return $this->value->format('Y-m-d\TH:i:s.uP');
    }

    /** @return array{local: string, timezone: string, utc: string} */
    public function jsonSerialize(): array
    {
        return [
            'local' => $this->toRfc3339(),
            'timezone' => $this->timezone(),
            'utc' => $this->value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }

    private static function canonicalLocalValue(string $value): string
    {
        $matches = [];

        if (preg_match(
            '/^(?<date>\d{4}-\d{2}-\d{2})[ T](?<time>\d{2}:\d{2}:\d{2})(?:\.(?<fraction>\d{1,6}))?$/D',
            $value,
            $matches,
        ) !== 1) {
            throw new InvalidLocalDateTime(
                'Local date and time must use YYYY-MM-DD HH:MM:SS with optional microseconds.',
            );
        }

        $fraction = str_pad((string) ($matches['fraction'] ?? ''), 6, '0');

        return sprintf('%s %s.%s', $matches['date'], $matches['time'], $fraction);
    }

    private static function ianaTimezone(string $identifier): DateTimeZone
    {
        $isNamedTimezone = $identifier === 'UTC'
            || in_array($identifier, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true);

        if (!$isNamedTimezone) {
            throw new InvalidTimezone(sprintf(
                '"%s" is not a recognized IANA timezone identifier; fixed offsets are not accepted.',
                $identifier,
            ));
        }

        return new DateTimeZone($identifier);
    }

    private static function assertSupportedYear(int $year): void
    {
        if ($year < 1 || $year > 9999) {
            throw new InvalidLocalDateTime('Years outside 0001 through 9999 are not supported.');
        }
    }

    private static function matchingInstantCount(string $canonical, DateTimeZone $timezone): int
    {
        $naive = DateTimeImmutable::createFromFormat(
            '!'.self::LOCAL_FORMAT,
            $canonical,
            new DateTimeZone('UTC'),
        );

        if ($naive === false) {
            return 0;
        }

        $naiveTimestamp = $naive->getTimestamp();
        $transitions = $timezone->getTransitions($naiveTimestamp - 604_800, $naiveTimestamp + 604_800);

        /** @var array<int, true> $offsets */
        $offsets = [];
        foreach ($transitions as $transition) {
            $offsets[$transition['offset']] = true;
        }

        $matches = 0;
        foreach (array_keys($offsets) as $offset) {
            $candidate = (new DateTimeImmutable('@'.(string) ($naiveTimestamp - $offset)))
                ->setTimezone($timezone);

            if ($candidate->format('Y-m-d H:i:s') === substr($canonical, 0, 19)) {
                ++$matches;
            }
        }

        return $matches;
    }
}
