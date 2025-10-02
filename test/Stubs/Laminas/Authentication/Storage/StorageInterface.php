<?php

declare(strict_types=1);

namespace Laminas\Authentication\Storage;

interface StorageInterface
{
    public function isEmpty(): bool;

    /**
     * @return mixed
     */
    public function read();

    /**
     * @param mixed $contents
     */
    public function write($contents): void;

    public function clear(): void;
}
