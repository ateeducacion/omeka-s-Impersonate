<?php

declare(strict_types=1);

namespace Laminas\Mvc;

class MvcEvent
{
    const EVENT_ROUTE = 'route';
    const EVENT_DISPATCH = 'dispatch';
    public $application;
    public $routeMatch;
    public $response;
    public $stopped = false;
    public function getApplication()
    {
        return $this->application;
    }
    public function getRouteMatch()
    {
        return $this->routeMatch;
    }
    public function getResponse()
    {
        return $this->response;
    }
    public function setResponse($response)
    {
        $this->response = $response;
    }
    public function stopPropagation($stop)
    {
        $this->stopped = $stop;
    }
}
