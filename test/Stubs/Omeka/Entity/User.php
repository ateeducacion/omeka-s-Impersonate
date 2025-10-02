<?php

declare(strict_types=1);

namespace Omeka\Entity;

class User
{
    private int $id;
    private string $email = '';
    private string $name = '';
    private string $role = '';

    public function __construct(int $id = 0, string $email = '', string $role = 'editor')
    {
        $this->id = $id;
        $this->email = $email ?: sprintf('user%d@example.com', $id);
        $this->role = $role;
        $this->name = '';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }
}
