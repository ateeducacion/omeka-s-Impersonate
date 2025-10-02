<?php

declare(strict_types=1);

namespace ImpersonateTest;

use Laminas\Authentication\Storage\StorageInterface;

/**
 * Simple in-memory authentication storage for testing purposes.
 */
final class InMemoryStorage implements StorageInterface
{
    /** @var mixed|null */
    private $data;

    public function isEmpty(): bool
    {
        return $this->data === null;
    }

    /** @return mixed|null */
    public function read()
    {
        return $this->data;
    }

    /** @param mixed $contents */
    public function write($contents): void
    {
        $this->data = $contents;
    }

    public function clear(): void
    {
        $this->data = null;
    }
}
