<?php

declare(strict_types=1);

namespace ImpersonateTest;

use Doctrine\ORM\EntityManagerInterface;
use Impersonate\Controller\Factory\ImpersonationControllerFactory;
use Impersonate\Controller\ImpersonationController;
use Impersonate\Service\Factory\ImpersonationServiceFactory;
use Impersonate\Service\ImpersonationService;
use Laminas\Authentication\AuthenticationService;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Session\SessionManager;
use Omeka\Permissions\Acl;
use Omeka\Settings\Settings;
use PHPUnit\Framework\TestCase;

class FactoryTest extends TestCase
{
    public function testServiceFactorySupportsBothSessionServicesAndFallback(): void
    {
        foreach (['Omeka\SessionManager', SessionManager::class, null] as $sessionName) {
            $services = new ServiceManager(['services' => [
                'Omeka\AuthenticationService' => new AuthenticationService(null, new InMemoryStorage()),
                'Omeka\EntityManager' => $this->createMock(EntityManagerInterface::class),
                'Omeka\Acl' => new Acl(),
                'Omeka\Settings' => new Settings(),
            ]]);
            if ($sessionName) {
                $services->setService($sessionName, new SessionManager());
            }
            $service = (new ImpersonationServiceFactory())($services, ImpersonationService::class);
            $this->assertFalse($service->currentUserCanManage());
            $services->setService(ImpersonationService::class, $service);
            $controller = (new ImpersonationControllerFactory())($services, ImpersonationController::class);
            $this->assertInstanceOf(ImpersonationController::class, $controller);
        }
    }
}
