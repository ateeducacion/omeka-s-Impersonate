<?php

declare(strict_types=1);

namespace Impersonate;

use Impersonate\Service\ImpersonationService;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Module\AbstractModule;

class Module extends AbstractModule
{
    public const RESOURCE_NAME = 'impersonate';
    public const PRIVILEGE_MANAGE = 'manage_impersonation';

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);
        $services = $event->getApplication()->getServiceManager();
        $acl = $services->get('Omeka\\Acl');
        if (!$acl->hasResource(self::RESOURCE_NAME)) {
            $acl->addResource(self::RESOURCE_NAME);
        }

        // Handle optional GET param `login_as=<id>` for admins only
        $em = $event->getApplication()->getEventManager();
        $em->attach(
            MvcEvent::EVENT_ROUTE,
            [$this, 'handleLoginAsParam'],
            100
        );
        $em->attach(
            MvcEvent::EVENT_DISPATCH,
            [$this, 'handleLoginAsParam'],
            100
        );
    }

    public function handleLoginAsParam(MvcEvent $event): void
    {
        $app = $event->getApplication();
        $services = $app->getServiceManager();
        $request = $app->getRequest();
        $routeMatch = $event->getRouteMatch();

        if (!$routeMatch || !method_exists($request, 'getQuery')) {
            return;
        }

        $routeName = (string) $routeMatch->getMatchedRouteName();
        if (strpos($routeName, 'admin') !== 0) {
            // Only enable on admin routes
            return;
        }

        /** @var ImpersonationService $impersonation */
        $impersonation = $services->get(ImpersonationService::class);

        // Support GET end via /admin/impersonate/end for convenience
        $uri = method_exists($request, 'getUri') ? $request->getUri() : null;
        $path = $uri ? (string) $uri->getPath() : '';
        $isGet = method_exists($request, 'isPost') ? !$request->isPost() : true;
        if ($isGet && strpos($path, '/admin/impersonate/end') === 0) {
            if ($impersonation->isImpersonating()) {
                try {
                    $impersonation->endImpersonation($this->getIpFromRequest($request));
                } catch (\Throwable $e) {
                    // ignore
                }
            }
            $response = $event->getResponse() ?: new \Laminas\Http\Response();
            $response->getHeaders()->addHeaderLine('Location', '/admin');
            $response->setStatusCode(302);
            $event->setResponse($response);
            $event->stopPropagation(true);
            return;
        }

        $loginAs = $request->getQuery('login_as', null);
        if ($loginAs === null || $loginAs === '') {
            return; // Not requested
        }

        // Basic sanitize
        $targetId = filter_var($loginAs, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$targetId) {
            return;
        }

        // Check permission via service (respects configured minimum role)
        if (!$impersonation->currentUserCanManage()) {
            return;
        }

        if ($impersonation->isImpersonating()) {
            return; // Avoid nested changes via GET
        }

        $entityManager = $services->get('Omeka\\EntityManager');
        $user = $entityManager->find(\Omeka\Entity\User::class, (int) $targetId);
        if (!$user instanceof \Omeka\Entity\User) {
            return;
        }

        if (!$impersonation->canImpersonate($user)) {
            return; // Respect module rules: not myself and not admins
        }

        try {
            $impersonation->startImpersonation($user, $this->getIpFromRequest($request));
        } catch (\Throwable $e) {
            return; // Silent ignore on any failure
        }

        // Redirect to same URL without the login_as param to prevent repeat
        $uri = clone $request->getUri();
        $query = $uri->getQuery();
        $params = [];
        if (is_string($query) && $query !== '') {
            parse_str($query, $params);
        }
        unset($params['login_as']);
        $uri->setQuery(http_build_query($params));

        $response = $event->getResponse();
        if (!$response) {
            $response = new \Laminas\Http\Response();
            $event->setResponse($response);
        }
        $response->getHeaders()->addHeaderLine('Location', $uri->toString());
        $response->setStatusCode(302);
        $event->stopPropagation(true);
    }

    private function getIpFromRequest($request): string
    {
        if (method_exists($request, 'getServer')) {
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

    public function install(ServiceLocatorInterface $serviceLocator): void
    {
        // Set default settings
        $settings = $serviceLocator->get('Omeka\\Settings');
        if ($settings->get('impersonate_min_role') === null) {
            $settings->set('impersonate_min_role', 'global_admin');
        }
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator): void
    {
        // No database changes to rollback.
    }

    public function getConfigForm(\Laminas\View\Renderer\PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\\Settings');
        $current = (string) ($settings->get('impersonate_min_role') ?? 'global_admin');
        $roles = [
            'researcher' => $renderer->translate('Researcher'),
            'author' => $renderer->translate('Author'),
            'reviewer' => $renderer->translate('Reviewer'),
            'editor' => $renderer->translate('Editor'),
            'site_admin' => $renderer->translate('Site Admin'),
            'global_admin' => $renderer->translate('Global Administrator'),
        ];
        $html = '<div class="field">'
            . '<label for="impersonate-min-role">'
            . $renderer->escapeHtml(
                $renderer->translate('Minimum role that can impersonate')
            )
            . '</label>'
            . '<div class="inputs">'
            . '<select name="impersonate[min_role]" id="impersonate-min-role">';
        foreach ($roles as $value => $label) {
            $selected = $value === $current ? ' selected' : '';
            $html .= '<option value="' . $renderer->escapeHtmlAttr($value) . '"' . $selected . '>'
                . $renderer->escapeHtml($label) . '</option>';
        }
        $html .= '</select>'
            . '<p class="explanation">'
            . $renderer->escapeHtml(
                $renderer->translate(
                    'Users with this role or higher can impersonate lower roles.'
                )
            )
            . '</p>'
            . '</div></div>';
        return $html;
    }

    public function handleConfigForm(\Laminas\Mvc\Controller\AbstractController $controller)
    {
        $request = $controller->getRequest();
        if (!method_exists($request, 'getPost')) {
            return;
        }
        $post = $request->getPost();
        $params = $post['impersonate'] ?? [];
        $minRole = isset($params['min_role']) ? (string) $params['min_role'] : 'global_admin';
        $allowed = ['researcher','author','reviewer','editor','site_admin','global_admin'];
        if (!in_array($minRole, $allowed, true)) {
            $minRole = 'global_admin';
        }
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\\Settings');
        $settings->set('impersonate_min_role', $minRole);
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach('*', 'view.layout', [$this, 'injectImpersonationBanner']);
    }

    public function injectImpersonationBanner(Event $event): void
    {
        $view = $event->getTarget();
        if (!$view instanceof PhpRenderer) {
            return;
        }

        $services = $this->getServiceLocator();
        /** @var ImpersonationService $impersonation */
        $impersonation = $services->get(ImpersonationService::class);

        $mvcEvent = $services->get('Application')->getMvcEvent();
        $routeMatch = $mvcEvent ? $mvcEvent->getRouteMatch() : null;
        if (!$routeMatch) {
            return;
        }

        $routeName = (string) $routeMatch->getMatchedRouteName();
        if (strpos($routeName, 'admin') !== 0) {
            return;
        }

        // Inject an "Impersonate" action/link into the Users browse page when allowed
        if ($impersonation->currentUserCanManage()) {
            $switchText = $view->translate('switch to');
            // Style for the inline text link next to the user name
            $view->headStyle()->appendStyle(
                '.impersonate-switch-link{' .
                'display:inline-block;' .
                'margin-left:.4rem;' .
                'padding:.1rem .4rem;' .
                'border-radius:.25rem;' .
                'background:#eef2ff !important;' .
                'border:1px solid #c7d2fe;' .
                'color:#1f2937 !important;' .
                'font-size:.85em;' .
                'line-height:1;' .
                'text-decoration:none !important;' .
                'vertical-align:baseline;' .
                '}' .
                '.impersonate-switch-link:hover,' .
                '.impersonate-switch-link:focus{' .
                'background:#c7d2fe !important;' .
                'color:#111827 !important;' .
                'text-decoration:none !important;' .
                'outline:none;' .
                '}'
            );
            $js = <<<'JS'
            (function($){
              function inject(){
                if (!$('body').is('.users.browse')) return;
                function currentUserId(){
                  var headerLink = $('#user .user-id a.user-show');
                  if (!headerLink.length) return null;
                  var m = headerLink.attr('href').match(/\/admin\/user\/(\d+)/);
                  return m ? parseInt(m[1], 10) : null;
                }
                function hasAdminInName($nameLink){
                  var t = ($nameLink.text() || '').toLowerCase();
                  return t.indexOf('admin') !== -1 || t.indexOf('super') !== -1;
                }
                var me = currentUserId();
                $('table.tablesaw tbody tr').each(function(){
                  var $row = $(this);
                  var $nameLink = $row.find('a[href^="/admin/user/"]').first();
                  var href = $nameLink.attr('href');
                  if (!href) return;
                  var m = href.match(/\/admin\/user\/(\d+)/);
                  if (!m) return;
                  var uid = parseInt(m[1], 10);
                  if (me && uid === me) return; // skip myself
                  if (hasAdminInName($nameLink)) return; // skip admins/supervisors

                  // Add icon in actions
                  var $actions = $row.find('ul.actions');
                  if ($actions.length && !$actions.find('a.impersonate-inline-link').length) {
                    var link = ''+
                      '<li>'+
                      '<a class="o-icon-user impersonate-inline-link" ' +
                      'href="/admin/user?login_as='+uid+'" title="Impersonate"></a>'+
                      '</li>';
                    $actions.append(link);
                  }

                  // Add text link next to name
                  if ($nameLink.length && !$row.find('a.impersonate-switch-link').length) {
                    var switchLink = $('<a/>', {
                      'class': 'impersonate-switch-link',
                      'href': '/admin/user?login_as=' + uid,
                      'text': ' · %%SWITCH_TEXT%%',
                      'title': 'Impersonate'
                    });
                    $nameLink.after(switchLink);
                  }
                });
              }
              $(inject);
              // retry a few times in case of delayed render
              var tries = 0, maxTries = 10;
              var t = setInterval(function(){
                tries++;
                inject();
                if (tries >= maxTries) clearInterval(t);
              }, 300);
              // Re-inject on dynamic changes
              var target = document.getElementById('content');
              if (target && 'MutationObserver' in window) {
                var obs = new MutationObserver(function(){ inject(); });
                obs.observe(target, {childList:true, subtree:true});
              }
            })(jQuery);
            JS;
            $js = str_replace('%%SWITCH_TEXT%%', addslashes($switchText), $js);
            // Append both to maximize chances across themes
            $view->headScript()->appendScript($js);
            $view->inlineScript()->appendScript($js);
        }

        // If impersonating, prepend the banner
        if ($impersonation->isImpersonating()) {
            $layout = $view->layout();
            $bannerHtml = $view->partial('impersonate/admin/impersonation/banner', [
                'impersonatedUser' => $impersonation->getImpersonatedUser(),
                'originalAdmin' => $impersonation->getOriginalAdmin(),
                'endToken' => $impersonation->getCsrfToken(ImpersonationService::CSRF_END),
            ]);
            // Prepend into content so it's rendered, then move it to the very top
            $layout->content = $bannerHtml . $layout->content;
            $moveTop = <<<'JS'
            (function($){
              $(function(){
                var b = $('.impersonate-banner').first();
                if (!b.length) return;
                var flex = $('div.flex').first();
                if (flex.length) {
                  flex.before(b);
                } else {
                  $('body').prepend(b);
                }
              });
            })(jQuery);
            JS;
            $view->inlineScript()->appendScript($moveTop);
        }
    }
}
