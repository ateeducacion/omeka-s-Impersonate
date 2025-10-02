<?php

declare(strict_types=1);

namespace Omeka\Permissions;

class Acl
{
    /** @var array<string, array<string, array<string, bool>>> */
    private array $rules = [];

    public function allow(string $role, string $resource, $privileges): void
    {
        foreach ((array) $privileges as $privilege) {
            $this->rules[$role][$resource][$privilege] = true;
        }
    }

    public function isAllowed($role, ?string $resource = null, ?string $privilege = null): bool
    {
        $roleName = is_object($role) && method_exists($role, 'getRole')
            ? (string) $role->getRole()
            : (string) $role;

        if ($resource === null || $privilege === null) {
            return false;
        }

        return !empty($this->rules[$roleName][$resource][$privilege]);
    }
}
