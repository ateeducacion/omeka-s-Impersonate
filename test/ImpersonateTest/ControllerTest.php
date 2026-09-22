<?php

declare(strict_types=1);

namespace ImpersonateTest;

use Doctrine\ORM\EntityManagerInterface;
use Impersonate\Controller\ImpersonationController;
use Impersonate\Service\ImpersonationService;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Http\Response;
use Omeka\Entity\User;
use Omeka\Mvc\Exception\PermissionDeniedException;
use PHPUnit\Framework\TestCase;

class ControllerTest extends TestCase
{
    public function testStartAndEndRejectGetRequests(): void
    {
        foreach (['startAction', 'endAction'] as $action) {
            $controller = $this->controller();
            $controller->request->method = 'GET';
            $response = $controller->$action();
            $this->assertSame(405, $response->status);
            $this->assertSame('POST', $response->headers['Allow']);
        }
    }

    public function testStartRejectsInvalidTokensIdentifiersAndMissingUsers(): void
    {
        foreach ([[false, '2', 400], [true, '', 400], [true, '-1', 400], [true, 'abc', 400],
            [true, null, 400], [true, '2', 404]] as [$valid, $id, $status]) {
            $service = $this->createMock(ImpersonationService::class);
            $service->method('isCsrfTokenValid')->willReturn($valid);
            $service->expects($this->never())->method('startImpersonation');
            $controller = $this->controller($service);
            $controller->request->post = ['csrf' => 'token', 'target_user_id' => $id];
            $this->assertSame($status, $controller->startAction()->status);
        }
    }

    public function testSuccessfulStartUsesProxyAddressAndRedirectsToAdmin(): void
    {
        $service = $this->createMock(ImpersonationService::class);
        $service->method('isCsrfTokenValid')->willReturn(true);
        $target = new User(2, 'editor@example.org', 'editor');
        $service->expects($this->once())->method('startImpersonation')->with($target, '192.0.2.1');
        $controller = $this->controller($service, $target);
        $controller->request->post = ['csrf' => 'token', 'target_user_id' => '2'];
        $controller->request->server = ['HTTP_X_FORWARDED_FOR' => '192.0.2.1, 192.0.2.2'];
        $this->assertSame('admin', $controller->startAction());
    }

    public function testStartMapsPermissionAndSessionFailuresToHttpStatus(): void
    {
        foreach ([new PermissionDeniedException('Denied'), new \RuntimeException('Active')] as $exception) {
            $service = $this->createMock(ImpersonationService::class);
            $service->method('isCsrfTokenValid')->willReturn(true);
            $service->method('startImpersonation')->willThrowException($exception);
            $controller = $this->controller($service, new User(2, 'a@example.org', 'author'));
            $controller->request->post = ['csrf' => 'token', 'target_user_id' => 2];
            $controller->request->server = ['REMOTE_ADDR' => '192.0.2.3'];
            $response = $controller->startAction();
            $this->assertSame($exception instanceof PermissionDeniedException ? 403 : 409, $response->status);
            $this->assertSame($exception->getMessage(), $response->content);
        }
    }

    public function testEndValidatesTokenRestoresSessionAndMapsFailures(): void
    {
        foreach ([null, new PermissionDeniedException('Denied'), new \RuntimeException('Gone')] as $exception) {
            $service = $this->createMock(ImpersonationService::class);
            $service->method('isCsrfTokenValid')->willReturn(true);
            $call = $service->expects($this->once())->method('endImpersonation')->with('unknown');
            if ($exception) {
                $call->willThrowException($exception);
            }
            $controller = $this->controller($service);
            $controller->request->post = ['csrf' => 'token'];
            $response = $controller->endAction();
            if ($exception) {
                $this->assertSame($exception instanceof PermissionDeniedException ? 403 : 500, $response->status);
            } else {
                $this->assertSame('admin/user', $response);
            }
        }
        $this->assertSame(400, $this->controller()->endAction()->status);
    }

    private function controller($service = null, $target = null): ImpersonationController
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn($target);
        $controller = new ImpersonationController(
            $entityManager,
            $service ?? $this->createMock(ImpersonationService::class)
        );
        $controller->request = new Request();
        $controller->request->method = 'POST';
        $controller->response = new Response();
        $controller->plugins = [
            'translate' => static function ($text) {
                return $text;
            },
            'params' => new class ($controller->request) {
                private $request;
                public function __construct($request)
                {
                    $this->request = $request;
                }
                public function fromPost($key = null)
                {
                    return $key === null ? $this->request->post : ($this->request->post[$key] ?? null);
                }
            },
            'messenger' => new class {
                public function addError($text)
                {
                }
                public function addSuccess($text)
                {
                }
            },
            'redirect' => new class {
                public function toRoute($route)
                {
                    return $route;
                }
            },
        ];
        return $controller;
    }
}
