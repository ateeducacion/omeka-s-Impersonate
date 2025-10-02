<?php

declare(strict_types=1);

namespace Impersonate\Service\Factory;

use Impersonate\Service\ImpersonationService;
use Laminas\Session\SessionManager;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class ImpersonationServiceFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ): ImpersonationService {
        // Resolve a SessionManager in a way that works across Omeka S versions
        // Some installs expose it as 'Omeka\\SessionManager', others as the Laminas class service.
        if ($container->has('Omeka\\SessionManager')) {
            $sessionManager = $container->get('Omeka\\SessionManager');
        } elseif ($container->has(SessionManager::class)) {
            $sessionManager = $container->get(SessionManager::class);
        } elseif ($container->has('Laminas\\Session\\SessionManager')) {
            $sessionManager = $container->get('Laminas\\Session\\SessionManager');
        } else {
            // Safe fallback: construct a default manager
            $sessionManager = new SessionManager();
        }

        return new ImpersonationService(
            $container->get('Omeka\\AuthenticationService'),
            $container->get('Omeka\\EntityManager'),
            $container->get('Omeka\\Acl'),
            $sessionManager,
            $container->get('Omeka\\Settings')
        );
    }
}
