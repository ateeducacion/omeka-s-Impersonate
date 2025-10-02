<?php

declare(strict_types=1);

namespace Laminas\Authentication;

use Laminas\Authentication\Storage\StorageInterface;

class AuthenticationService
{
    private StorageInterface $storage;

    public function __construct($adapter = null, ?StorageInterface $storage = null)
    {
        if ($storage === null) {
            throw new \InvalidArgumentException('A storage implementation is required in the test environment.');
        }

        $this->storage = $storage;
    }

    public function getIdentity()
    {
        return $this->storage->read();
    }

    public function getStorage(): StorageInterface
    {
        return $this->storage;
    }
}
