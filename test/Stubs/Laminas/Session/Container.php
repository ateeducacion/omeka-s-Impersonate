<?php

declare(strict_types=1);

namespace Laminas\Session;

use ArrayAccess;
use Laminas\Session\Storage\ArrayStorage;

class Container implements ArrayAccess
{
    private string $name;
    private SessionManager $manager;
    /** @var array */
    public $tokenList = [];
    /** @var mixed */
    public $hash;

    public function __construct(string $name, ?SessionManager $manager = null)
    {
        if ($manager === null) {
            throw new \InvalidArgumentException('Container requires a SessionManager in the test environment.');
        }

        $this->name = $name;
        $this->manager = $manager;
        $storage = $this->manager->getStorage();
        if (!$storage->offsetExists($this->name)) {
            $storage->offsetSet($this->name, []);
        }
    }

    #[\ReturnTypeWillChange]
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->getNamespace());
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        $namespace = $this->getNamespace();
        return $namespace[$offset] ?? null;
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value): void
    {
        $storage = $this->manager->getStorage();
        $namespace = $storage->offsetGet($this->name);
        $namespace[$offset] = $value;
        $storage->offsetSet($this->name, $namespace);
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset): void
    {
        $storage = $this->manager->getStorage();
        $namespace = $storage->offsetGet($this->name);
        unset($namespace[$offset]);
        $storage->offsetSet($this->name, $namespace);
    }

    public function getArrayCopy(): array
    {
        return $this->getNamespace();
    }

    public function setExpirationSeconds($seconds): void
    {
        // No-op for test stub
    }

    public function __get(string $name)
    {
        $ns = $this->getNamespace();
        return $ns[$name] ?? null;
    }

    public function __set(string $name, $value): void
    {
        $storage = $this->manager->getStorage();
        $ns = $storage->offsetGet($this->name);
        $ns[$name] = $value;
        $storage->offsetSet($this->name, $ns);
    }

    public function __isset(string $name): bool
    {
        $ns = $this->getNamespace();
        return array_key_exists($name, $ns);
    }

    public function __unset(string $name): void
    {
        $storage = $this->manager->getStorage();
        $ns = $storage->offsetGet($this->name);
        unset($ns[$name]);
        $storage->offsetSet($this->name, $ns);
    }

    private function getNamespace(): array
    {
        /** @var ArrayStorage $storage */
        $storage = $this->manager->getStorage();
        return (array) $storage->offsetGet($this->name);
    }
}
