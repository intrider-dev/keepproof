<?php

declare(strict_types=1);

namespace Tests\Fakes;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use LogicException;
use RuntimeException;

final class FailingBusDispatcher implements Dispatcher
{
    public function dispatch(mixed $command): never
    {
        throw new RuntimeException('Synthetic queue dispatch outage.');
    }

    public function dispatchSync(mixed $command, mixed $handler = null): never
    {
        throw new LogicException('Synchronous dispatch is not supported by this failure fake.');
    }

    public function dispatchNow(mixed $command, mixed $handler = null): never
    {
        throw new LogicException('Immediate dispatch is not supported by this failure fake.');
    }

    public function dispatchAfterResponse(mixed $command, mixed $handler = null): never
    {
        throw new LogicException('Deferred dispatch is not supported by this failure fake.');
    }

    /** @param Collection<int, mixed>|array<int, mixed>|null $jobs */
    public function chain(mixed $jobs = null): never
    {
        throw new LogicException('Job chaining is not supported by this failure fake.');
    }

    public function hasCommandHandler(mixed $command): bool
    {
        return false;
    }

    public function getCommandHandler(mixed $command): null
    {
        return null;
    }

    /** @param list<mixed> $pipes */
    public function pipeThrough(array $pipes): static
    {
        return $this;
    }

    /** @param array<class-string, class-string> $map */
    public function map(array $map): static
    {
        return $this;
    }
}
