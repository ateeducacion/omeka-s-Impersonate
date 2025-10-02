<?php

declare(strict_types=1);

namespace Impersonate\Controller;

use Impersonate\Service\ImpersonationService;
use Laminas\Http\PhpEnvironment\Request as PhpRequest;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Entity\User;
use Omeka\Mvc\Exception\PermissionDeniedException;
use Doctrine\ORM\EntityManagerInterface;

class ImpersonationController extends AbstractActionController
{
    private EntityManagerInterface $entityManager;
    private ImpersonationService $impersonationService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ImpersonationService $impersonationService
    ) {
        $this->entityManager = $entityManager;
        $this->impersonationService = $impersonationService;
    }

    public function startAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return $this->methodNotAllowed();
        }

        $params = $this->params()->fromPost();
        $csrfToken = is_array($params) ? ($params['csrf'] ?? null) : null;
        if (!$this->impersonationService->isCsrfTokenValid(ImpersonationService::CSRF_START, $csrfToken)) {
            return $this->errorResponse(Response::STATUS_CODE_400, $this->translate('Invalid CSRF token.'));
        }

        $targetId = $this->sanitizeUserId($params['target_user_id'] ?? null);
        if (!$targetId) {
            return $this->errorResponse(
                Response::STATUS_CODE_400,
                $this->translate('A valid target user id is required.')
            );
        }

        $targetUser = $this->entityManager->find(User::class, $targetId);
        if (!$targetUser instanceof User) {
            return $this->errorResponse(
                Response::STATUS_CODE_404,
                $this->translate('The requested user was not found.')
            );
        }

        try {
            $this->impersonationService->startImpersonation($targetUser, $this->getIpAddress());
        } catch (PermissionDeniedException $exception) {
            $message = $exception->getMessage() ?: $this->translate('You are not allowed to impersonate this user.');
            $this->messenger()->addError($message);
            return $this->errorResponse(Response::STATUS_CODE_403, $message);
        } catch (\RuntimeException $exception) {
            $this->messenger()->addError($exception->getMessage());
            return $this->errorResponse(Response::STATUS_CODE_409, $exception->getMessage());
        }

        $displayName = $targetUser->getName() ?: $targetUser->getEmail();
        $this->messenger()->addSuccess(sprintf($this->translate('You are now impersonating %s.'), $displayName));

        return $this->redirect()->toRoute('admin');
    }

    public function endAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return $this->methodNotAllowed();
        }

        $csrfToken = $this->params()->fromPost('csrf');
        if (!$this->impersonationService->isCsrfTokenValid(ImpersonationService::CSRF_END, $csrfToken)) {
            return $this->errorResponse(Response::STATUS_CODE_400, $this->translate('Invalid CSRF token.'));
        }

        try {
            $this->impersonationService->endImpersonation($this->getIpAddress());
        } catch (PermissionDeniedException $exception) {
            $message = $exception->getMessage() ?: $this->translate('No impersonation session is active.');
            $this->messenger()->addError($message);
            return $this->errorResponse(Response::STATUS_CODE_403, $message);
        } catch (\RuntimeException $exception) {
            $this->messenger()->addError($exception->getMessage());
            return $this->errorResponse(Response::STATUS_CODE_500, $exception->getMessage());
        }

        $this->messenger()->addSuccess(
            $this->translate('Impersonation ended. You have been restored as administrator.')
        );

        return $this->redirect()->toRoute('admin/user');
    }

    private function sanitizeUserId($raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $filtered = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $filtered !== false ? (int) $filtered : null;
    }

    private function getIpAddress(): string
    {
        $request = $this->getRequest();
        if ($request instanceof PhpRequest) {
            $forwarded = (string) $request->getServer('HTTP_X_FORWARDED_FOR', '');
            if ($forwarded) {
                $parts = array_map('trim', explode(',', $forwarded));
                if (!empty($parts[0])) {
                    return $parts[0];
                }
            }

            $remote = (string) $request->getServer('REMOTE_ADDR', '');
            if ($remote !== '') {
                return $remote;
            }
        }

        return 'unknown';
    }

    private function errorResponse(int $statusCode, string $message)
    {
        $response = $this->getResponse();
        $response->setStatusCode($statusCode);
        $response->setContent($message);
        return $response;
    }

    private function methodNotAllowed()
    {
        $response = $this->errorResponse(Response::STATUS_CODE_405, $this->translate('Method not allowed.'));
        $response->getHeaders()->addHeaderLine('Allow', 'POST');
        return $response;
    }
}
