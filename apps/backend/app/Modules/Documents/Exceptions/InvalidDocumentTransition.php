<?php

declare(strict_types=1);

namespace App\Modules\Documents\Exceptions;

use DomainException;

final class InvalidDocumentTransition extends DomainException {}
