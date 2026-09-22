<?php

declare(strict_types=1);

namespace Laminas\Mvc\Controller;

abstract class AbstractController
{
    public $request;
    public $response;
    public $plugins = [];
    public function getRequest()
    {
        return $this->request;
    }
    public function getResponse()
    {
        return $this->response;
    }
    public function __call($name, $args)
    {
        return is_callable($this->plugins[$name]) ? ($this->plugins[$name])(...$args) : $this->plugins[$name];
    }
}
