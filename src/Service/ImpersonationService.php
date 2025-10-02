<?php

declare(strict_types=1);

namespace Impersonate\Service;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Impersonate\Module;
use Laminas\Authentication\AuthenticationService;
use Laminas\Session\Container;
use Laminas\Session\SessionManager;
use Laminas\Validator\Csrf;
use Omeka\Entity\User;
use Omeka\Mvc\Exception\PermissionDeniedException;
use Omeka\Permissions\Acl;
use Omeka\Settings\Settings;

class ImpersonationService
{
    public const SESSION_NAMESPACE = 'impersonate';
    public const SESSION_ORIGINAL = 'original';
    public const SESSION_IMPERSONATED = 'impersonated';

    public const CSRF_START = 'impersonate_start';
    public const CSRF_END = 'impersonate_end';

    private AuthenticationService $authenticationService;
    private EntityManagerInterface $entityManager;
    private Acl $acl;
    private SessionManager $sessionManager;
    private ?Settings $settings = null;
    private ?Container $sessionContainer = null;
    private array $csrfTokens = [];
    /** @var array<string, Container> */
    private array $csrfContainers = [];

    public function __construct(
        AuthenticationService $authenticationService,
        EntityManagerInterface $entityManager,
        Acl $acl,
        SessionManager $sessionManager,
        $settings = null
    ) {
        $this->authenticationService = $authenticationService;
        $this->entityManager = $entityManager;
        $this->acl = $acl;
        $this->sessionManager = $sessionManager;
        if ($settings instanceof Settings) {
            $this->settings = $settings;
        }
    }

    public function currentUser(): ?User
    {
        $identity = $this->authenticationService->getIdentity();
        return $identity instanceof User ? $identity : null;
    }

    public function currentUserCanManage(): bool
    {
        $user = $this->currentUser();
        if (!$user) {
            return false;
        }
        // Allow if ACL grants it OR role meets configured minimum
        $role = $user->getRole();
        if ($this->acl->isAllowed($role, Module::RESOURCE_NAME, Module::PRIVILEGE_MANAGE)) {
            return true;
        }
        $minRole = 'global_admin';
        if ($this->settings) {
            $configured = $this->settings->get('impersonate_min_role');
            if (is_string($configured) && $configured !== '') {
                $minRole = $configured;
            }
        }
        return $this->roleRank($role) >= $this->roleRank($minRole);
    }

    public function canImpersonate(User $target): bool
    {
        if (!$this->currentUserCanManage()) {
            return false;
        }
        $current = $this->currentUser();
        if ($current && $target->getId() === $current->getId()) {
            return false;
        }
        $currentRole = $current ? $current->getRole() : '';
        $targetRole = $target->getRole();
        // Only allow impersonating strictly lower roles
        return $this->roleRank($targetRole) < $this->roleRank($currentRole);
    }

    public function isImpersonating(): bool
    {
        $state = $this->getSessionContainer()->offsetGet(self::SESSION_ORIGINAL);
        return is_array($state) && isset($state['user_id']);
    }

    public function getOriginalAdmin(): ?User
    {
        $state = $this->getSessionContainer()->offsetGet(self::SESSION_ORIGINAL);
        if (!is_array($state) || empty($state['user_id'])) {
            return null;
        }

        return $this->entityManager->find(User::class, (int) $state['user_id']);
    }

    public function getImpersonatedUser(): ?User
    {
        if (!$this->isImpersonating()) {
            return null;
        }

        $state = $this->getSessionContainer()->offsetGet(self::SESSION_IMPERSONATED);
        if (!is_array($state) || empty($state['user_id'])) {
            return $this->currentUser();
        }

        $user = $this->entityManager->find(User::class, (int) $state['user_id']);
        return $user instanceof User ? $user : $this->currentUser();
    }

    public function startImpersonation(User $target, string $ipAddress): void
    {
        if (!$this->canImpersonate($target)) {
            throw new PermissionDeniedException('You do not have permission to impersonate this user.');
        }

        if ($this->isImpersonating()) {
            throw new \RuntimeException(
                'An impersonation session is already active. '
                . 'End it before starting a new one.'
            );
        }

        $currentUser = $this->currentUser();
        if (!$currentUser) {
            throw new PermissionDeniedException('You must be authenticated to impersonate another user.');
        }

        $session = $this->getSessionContainer();
        $session->offsetSet(self::SESSION_ORIGINAL, [
            'user_id' => $currentUser->getId(),
            'role' => $currentUser->getRole(),
            'session_id' => session_id(),
            'started_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
        $session->offsetSet(self::SESSION_IMPERSONATED, [
            'user_id' => $target->getId(),
            'started_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        $this->sessionManager->regenerateId(true);
        $this->authenticationService->getStorage()->write($target);
    }

    public function endImpersonation(string $ipAddress): void
    {
        if (!$this->isImpersonating()) {
            throw new PermissionDeniedException('Impersonation session not found.');
        }

        $session = $this->getSessionContainer();
        $original = $session->offsetGet(self::SESSION_ORIGINAL);
        $impersonated = $session->offsetGet(self::SESSION_IMPERSONATED);

        if (!is_array($original) || empty($original['user_id'])) {
            throw new \RuntimeException('The original administrator session could not be restored.');
        }

        $originalUser = $this->entityManager->find(User::class, (int) $original['user_id']);
        if (!$originalUser instanceof User) {
            throw new \RuntimeException('The original administrator account no longer exists.');
        }

        $targetUserId = is_array($impersonated) && !empty($impersonated['user_id'])
            ? (int) $impersonated['user_id']
            : ($this->currentUser() ? $this->currentUser()->getId() : 0);

        $this->authenticationService->getStorage()->write($originalUser);
        $this->sessionManager->regenerateId(true);

        $session->offsetUnset(self::SESSION_ORIGINAL);
        $session->offsetUnset(self::SESSION_IMPERSONATED);
    }

    public function getCsrfToken(string $name): string
    {
        if (!isset($this->csrfTokens[$name])) {
            $validator = $this->buildCsrfValidator($name);
            $this->csrfTokens[$name] = $validator->getHash();
        }

        return $this->csrfTokens[$name];
    }

    public function isCsrfTokenValid(string $name, ?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $validator = $this->buildCsrfValidator($name);
        return $validator->isValid($token);
    }

    private function getSessionContainer(): Container
    {
        if (!$this->sessionContainer instanceof Container) {
            $this->sessionContainer = new Container(self::SESSION_NAMESPACE, $this->sessionManager);
        }

        return $this->sessionContainer;
    }

    private function buildCsrfValidator(string $name): Csrf
    {
        $validator = new Csrf([
            'name' => $name,
            'timeout' => 900,
        ]);
        // Ensure the validator uses our SessionManager-backed container, stable per session name
        $sessionName = $validator->getSessionName();
        if (!isset($this->csrfContainers[$sessionName])) {
            $this->csrfContainers[$sessionName] = new Container($sessionName, $this->sessionManager);
        }
        $validator->setSession($this->csrfContainers[$sessionName]);
        return $validator;
    }

    private function roleRank(string $role): int
    {
        // Ordered from lowest to highest privileges
        static $order = [
            'researcher' => 1,
            'author' => 2,
            'reviewer' => 3,
            'editor' => 4,
            'site_admin' => 5,
            'global_admin' => 6,
            'super' => 7,
        ];
        return $order[$role] ?? 0;
    }
}
