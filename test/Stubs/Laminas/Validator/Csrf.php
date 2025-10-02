<?php

declare(strict_types=1);

namespace Laminas\Validator;

class Csrf
{
    private string $name;
    private static array $tokens = [];

    public function __construct(array $options)
    {
        if (empty($options['name'])) {
            throw new \InvalidArgumentException('CSRF validator requires a name.');
        }
        $this->name = (string) $options['name'];
    }

    public function getHash(): string
    {
        if (!isset(self::$tokens[$this->name])) {
            self::$tokens[$this->name] = bin2hex(random_bytes(16));
        }

        return self::$tokens[$this->name];
    }

    public function isValid($value): bool
    {
        $token = self::$tokens[$this->name] ?? null;
        if ($token === null) {
            $token = $this->getHash();
        }

        return hash_equals($token, (string) $value);
    }
}
