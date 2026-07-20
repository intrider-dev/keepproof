<?php

declare(strict_types=1);

namespace App\Modules\Shared\Configuration;

use LogicException;

final class FrontendUrl
{
    public static function base(): string
    {
        $configured = config('app.frontend_url');

        if (! is_string($configured) || filter_var($configured, FILTER_VALIDATE_URL) === false) {
            throw new LogicException('The app.frontend_url configuration must be an absolute URL.');
        }

        return rtrim($configured, '/');
    }
}
