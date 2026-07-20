<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Support;

use LogicException;

final class SupportedLocales
{
    /** @return non-empty-list<string> */
    public static function all(): array
    {
        $configured = config('app.supported_locales');

        if (! is_array($configured)) {
            throw new LogicException('The app.supported_locales configuration must be an array.');
        }

        $locales = [];

        foreach ($configured as $locale) {
            if (! is_string($locale) || $locale === '') {
                throw new LogicException('Every supported locale must be a non-empty string.');
            }

            $locales[] = $locale;
        }

        if ($locales === []) {
            throw new LogicException('At least one supported locale must be configured.');
        }

        return $locales;
    }
}
