<?php

declare(strict_types=1);

namespace Impersonate\Controller\Factory;

use Impersonate\Controller\ImpersonationController;
use Impersonate\Service\ImpersonationService;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class ImpersonationControllerFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ): ImpersonationController {
        return new ImpersonationController(
            $container->get('Omeka\\EntityManager'),
            $container->get(ImpersonationService::class)
        );
    }
}
