<?php

declare(strict_types=1);

namespace Omeka\Settings;

class Settings
{
    public $values = [];
    public function get($key, $default = null)
    {
        return $this->values[$key] ?? $default;
    }
    public function set($key, $value)
    {
        $this->values[$key] = $value;
    }
}
