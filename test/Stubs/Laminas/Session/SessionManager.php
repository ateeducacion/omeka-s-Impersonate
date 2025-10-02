<?php

declare(strict_types=1);

namespace Laminas\Session;

use Laminas\Session\Storage\ArrayStorage;

class SessionManager
{
    private ArrayStorage $storage;

    public function __construct()
    {
        $this->storage = new ArrayStorage();
    }

    public function setStorage(ArrayStorage $storage): void
    {
        $this->storage = $storage;
    }

    public function getStorage(): ArrayStorage
    {
        return $this->storage;
    }

    public function regenerateId(bool $deleteOldSession = false): void
    {
        // No-op for the test stub.
    }
}
