<?php

declare(strict_types=1);

namespace ImpersonateTest;

use Impersonate\Module;
use Impersonate\Service\ImpersonationService;
use Laminas\Authentication\AuthenticationService;
use Laminas\Authentication\Storage\StorageInterface;
use Laminas\Session\SessionManager;
use Omeka\Entity\User;
use Omeka\Mvc\Exception\PermissionDeniedException;
use Omeka\Permissions\Acl;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Doctrine\ORM\EntityManagerInterface;

final class ImpersonationServiceTest extends TestCase
{
    private AuthenticationService $authenticationService;
    /** @var EntityManagerInterface&MockObject */
    private $entityManager;
    private Acl $acl;
    private SessionManager $sessionManager;
    private InMemoryStorage $storage;
    private ImpersonationService $service;
    private User $admin;
    private User $target;

    protected function setUp(): void
    {
        $this->storage = new InMemoryStorage();
        $this->authenticationService = new AuthenticationService(null, $this->storage);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->acl = new Acl();
        $this->acl->allow('global_admin', Module::RESOURCE_NAME, Module::PRIVILEGE_MANAGE);

        $this->sessionManager = new SessionManager();
        $this->service = new ImpersonationService(
            $this->authenticationService,
            $this->entityManager,
            $this->acl,
            $this->sessionManager
        );

        $this->admin = new User(1, 'admin@example.com', 'global_admin');
        $this->admin->setName('Admin User');
        $this->target = new User(2, 'editor@example.com', 'editor');
        $this->target->setName('Editor User');

        $this->storage->write($this->admin);

        $this->entityManager
            ->method('find')
            ->willReturnCallback(function (string $entity, $id) {
                if ($entity !== User::class) {
                    return null;
                }
                if ((int) $id === $this->admin->getId()) {
                    return $this->admin;
                }
                if ((int) $id === $this->target->getId()) {
                    return $this->target;
                }
                return null;
            });
    }

    public function testAdminWithPrivilegeCanStartImpersonation(): void
    {
        $this->service->startImpersonation($this->target, '127.0.0.1');

        $this->assertTrue($this->service->isImpersonating());
        $this->assertSame($this->target, $this->service->getImpersonatedUser());
        $this->assertSame($this->target, $this->storage->read());
    }

    public function testEndImpersonationRestoresOriginalUser(): void
    {
        $this->service->startImpersonation($this->target, '127.0.0.1');
        $this->service->endImpersonation('127.0.0.1');

        $this->assertFalse($this->service->isImpersonating());
        $this->assertSame($this->admin, $this->storage->read());
    }

    public function testCannotImpersonateSuperRole(): void
    {
        $super = new User(3, 'super@example.com', 'super');
        $this->expectException(PermissionDeniedException::class);
        $this->service->startImpersonation($super, '127.0.0.1');
    }

    public function testNonPrivilegedUserCannotImpersonate(): void
    {
        $regularUser = new User(4, 'user@example.com', 'editor');
        $this->storage->write($regularUser);

        $this->expectException(PermissionDeniedException::class);
        $this->service->startImpersonation($this->target, '127.0.0.1');
    }

    public function testCsrfTokenValidation(): void
    {
        $token = $this->service->getCsrfToken(ImpersonationService::CSRF_START);
        $this->assertTrue($this->service->isCsrfTokenValid(ImpersonationService::CSRF_START, $token));
        $this->assertFalse($this->service->isCsrfTokenValid(ImpersonationService::CSRF_START, 'invalid'));
    }

    public function testCannotStartNewImpersonationWhenOneIsActive(): void
    {
        $this->service->startImpersonation($this->target, '127.0.0.1');

        $this->expectException(\RuntimeException::class);
        $this->service->startImpersonation($this->target, '127.0.0.1');
    }
}
