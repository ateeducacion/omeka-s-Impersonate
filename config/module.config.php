<?php

declare(strict_types=1);

namespace Impersonate;

use Laminas\Router\Http\Literal;

return [
    'view_manager' => [
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
    ],
    'service_manager' => [
        'factories' => [
            Service\ImpersonationService::class => Service\Factory\ImpersonationServiceFactory::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            Controller\ImpersonationController::class => Controller\Factory\ImpersonationControllerFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'impersonate' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/impersonate',
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'start' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/start',
                                    'defaults' => [
                                        'controller' => Controller\ImpersonationController::class,
                                        'action' => 'start',
                                    ],
                                ],
                            ],
                            'end' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/end',
                                    'defaults' => [
                                        'controller' => Controller\ImpersonationController::class,
                                        'action' => 'end',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'acl' => [
        'resources' => [
            Module::RESOURCE_NAME => [],
            Controller\ImpersonationController::class => [
                'privileges' => [
                    Module::PRIVILEGE_MANAGE,
                    'start',
                    'end',
                ],
            ],
        ],
        'allow' => [
            [
                'roles' => ['global_admin'],
                'resource' => Module::RESOURCE_NAME,
                'privileges' => [Module::PRIVILEGE_MANAGE],
            ],
            [
                'roles' => ['global_admin'],
                'resource' => Controller\ImpersonationController::class,
                'privileges' => ['start'],
            ],
            [
                // Allow any role to end an active impersonation
                'roles' => null,
                'resource' => Controller\ImpersonationController::class,
                'privileges' => ['end'],
            ],
        ],
    ],
];
