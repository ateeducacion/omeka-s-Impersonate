<?php

declare(strict_types=1);

namespace ImpersonateTest;

use Doctrine\ORM\EntityManagerInterface;
use Impersonate\Module;
use Impersonate\Service\ImpersonationService;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceManager;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Entity\User;
use Omeka\Settings\Settings;
use PHPUnit\Framework\TestCase;

class ModuleTest extends TestCase
{
    private function event($service, ?User $target = null, string $route = 'admin/user'): MvcEvent
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn($target);
        $services = new ServiceManager(['services' => [
            ImpersonationService::class => $service,
            'Omeka\EntityManager' => $entityManager,
        ]]);
        $request = new Request();
        $request->query = ['login_as' => '2'];
        $request->path = '/omeka/admin/user';
        $event = new MvcEvent();
        $event->application = new class ($services, $request) {
            private $services;
            public $request;
            public $calls = [];
            public function __construct($services, $request)
            {
                $this->services = $services;
                $this->request = $request;
            }
            public function getServiceManager()
            {
                return $this->services;
            }
            public function getRequest()
            {
                return $this->request;
            }
            public function getEventManager()
            {
                return $this;
            }
            public function attach(...$args)
            {
                $this->calls[] = $args;
            }
        };
        $event->routeMatch = new class ($route) {
            private $route;
            public function __construct($route)
            {
                $this->route = $route;
            }
            public function getMatchedRouteName()
            {
                return $this->route;
            }
        };
        return $event;
    }

    public function testLoginAsStartsSessionAndPreservesInstallationPrefix(): void
    {
        $service = $this->createMock(ImpersonationService::class);
        $service->method('currentUserCanManage')->willReturn(true);
        $service->method('canImpersonate')->willReturn(true);
        $target = new User(2, 'a@example.org', 'author');
        $service->expects($this->once())->method('startImpersonation')->with($target, '192.0.2.1');
        $event = $this->event($service, $target);
        $event->application->request->server = ['HTTP_X_FORWARDED_FOR' => '192.0.2.1, 192.0.2.2'];
        (new Module())->handleLoginAsParam($event);
        $this->assertSame(302, $event->response->status);
        $this->assertSame('/omeka/admin', $event->response->headers['Location']);
        $this->assertTrue($event->stopped);
    }

    public function testLoginAsIgnoresIneligibleRequests(): void
    {
        $cases = ['no-route', 'public', 'missing', 'invalid', 'denied',
            'active', 'missing-user', 'equal-role', 'failure'];
        foreach ($cases as $case) {
            $service = $this->createMock(ImpersonationService::class);
            $service->method('currentUserCanManage')->willReturn($case !== 'denied');
            $service->method('isImpersonating')->willReturn($case === 'active');
            $service->method('canImpersonate')->willReturn($case !== 'equal-role');
            if ($case === 'failure') {
                $service->method('startImpersonation')->willThrowException(new \RuntimeException('Failure'));
            } else {
                $service->expects($this->never())->method('startImpersonation');
            }
            $target = $case === 'missing-user' ? null : new User(2, 'a@example.org', 'author');
            $event = $this->event($service, $target, $case === 'public' ? 'site' : 'admin');
            if ($case === 'no-route') {
                $event->routeMatch = null;
            }
            if ($case === 'missing') {
                $event->application->request->query = [];
            }
            if ($case === 'invalid') {
                $event->application->request->query = ['login_as' => '-1'];
            }
            (new Module())->handleLoginAsParam($event);
            $this->assertFalse($event->stopped, $case);
        }
    }

    public function testEndRouteRestoresSessionAndAlwaysReturnsToAdmin(): void
    {
        foreach ([false, true] as $fails) {
            $service = $this->createMock(ImpersonationService::class);
            $service->method('isImpersonating')->willReturn(true);
            $call = $service->expects($this->once())->method('endImpersonation')->with('192.0.2.3');
            if ($fails) {
                $call->willThrowException(new \RuntimeException('Gone'));
            }
            $event = $this->event($service);
            $event->application->request->path = '/omeka/admin/impersonate/end';
            $event->application->request->server = ['REMOTE_ADDR' => '192.0.2.3'];
            (new Module())->handleLoginAsParam($event);
            $this->assertSame('/omeka/admin', $event->response->headers['Location']);
            $this->assertTrue($event->stopped);
        }
    }

    public function testConfigurationDefaultsRenderingAndRoleValidation(): void
    {
        $settings = new Settings();
        $services = new ServiceManager(['services' => ['Omeka\Settings' => $settings]]);
        $module = new Module();
        $module->setServiceLocator($services);
        $module->install($services);
        $this->assertSame('global_admin', $settings->get('impersonate_min_role'));
        $settings->set('impersonate_min_role', 'editor');
        $module->install($services);
        $this->assertSame('editor', $settings->get('impersonate_min_role'));
        $this->assertStringContainsString('value="editor" selected', $module->getConfigForm(new PhpRenderer()));
        $controller = new class extends \Laminas\Mvc\Controller\AbstractController {
        };
        $controller->request = new Request();
        foreach (['author' => 'author', 'invalid' => 'global_admin'] as $input => $expected) {
            $controller->request->post = ['impersonate' => ['min_role' => $input]];
            $module->handleConfigForm($controller);
            $this->assertSame($expected, $settings->get('impersonate_min_role'));
        }
        $controller->request = new \stdClass();
        $module->handleConfigForm($controller);
        $this->assertIsArray($module->getConfig());
    }

    public function testBootstrapRegistersResourceAndBothRequestListeners(): void
    {
        $event = $this->event($this->createMock(ImpersonationService::class));
        $acl = new class {
            public $resource;
            public function hasResource($resource)
            {
                return false;
            }
            public function addResource($resource)
            {
                $this->resource = $resource;
            }
        };
        $event->application->getServiceManager()->setService('Omeka\Acl', $acl);
        (new Module())->onBootstrap($event);
        $this->assertSame('impersonate', $acl->resource);
        $this->assertSame(['route', 'dispatch'], array_column($event->application->calls, 0));
        $events = $this->createMock(SharedEventManagerInterface::class);
        $events->expects($this->once())->method('attach')->with('*', 'view.layout', $this->isType('array'));
        (new Module())->attachListeners($events);
    }

    public function testBannerAndSwitchControlsRenderOnlyOnAdminRoutes(): void
    {
        $service = $this->createMock(ImpersonationService::class);
        $service->method('currentUserCanManage')->willReturn(true);
        $service->method('isImpersonating')->willReturn(true);
        $service->method('getCsrfToken')->willReturn('csrf');
        $mvcEvent = $this->event($service);
        $services = $mvcEvent->application->getServiceManager();
        $services->setService('Application', new class ($mvcEvent) {
            private $event;
            public function __construct($event)
            {
                $this->event = $event;
            }
            public function getMvcEvent()
            {
                return $this->event;
            }
        });
        $module = new Module();
        $module->setServiceLocator($services);
        $module->injectImpersonationBanner(new Event('view.layout', new \stdClass()));
        $view = new PhpRenderer();
        $module->injectImpersonationBanner(new Event('view.layout', $view));
        $this->assertSame('<banner>Active</banner>Content', $view->content);
        $this->assertContains('appendStyle', array_column($view->calls, 0));
        $this->assertContains('appendScript', array_column($view->calls, 0));
        $mvcEvent->routeMatch = null;
        $view = new PhpRenderer();
        $module->injectImpersonationBanner(new Event('view.layout', $view));
        $this->assertSame([], $view->calls);
    }
}
