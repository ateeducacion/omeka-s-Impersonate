<?php

declare(strict_types=1);

namespace Laminas\EventManager;

interface SharedEventManagerInterface
{
    public function attach($identifier, $event, $listener, $priority = 1);
}
