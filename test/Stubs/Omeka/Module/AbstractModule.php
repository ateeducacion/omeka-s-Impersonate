<?php

declare(strict_types=1);

namespace Omeka\Module;

abstract class AbstractModule
{
    private $services;
    public function setServiceLocator($services)
    {
        $this->services = $services;
    }
    public function getServiceLocator()
    {
        return $this->services;
    }
    public function onBootstrap(\Laminas\Mvc\MvcEvent $event)
    {
        $this->setServiceLocator($event->getApplication()->getServiceManager());
    }
}
