<?php

declare(strict_types=1);

namespace Laminas\EventManager;

class Event
{
    private $target;
    public function __construct($name = null, $target = null)
    {
        $this->target = $target;
    }
    public function getTarget()
    {
        return $this->target;
    }
}
