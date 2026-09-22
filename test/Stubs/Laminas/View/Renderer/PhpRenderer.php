<?php

declare(strict_types=1);

namespace Laminas\View\Renderer;

class PhpRenderer
{
    public $calls = [];
    public $content = 'Content';
    public function __call($name, $args)
    {
        $this->calls[] = [$name, $args];
        if (in_array($name, ['headStyle', 'headScript', 'inlineScript', 'layout'])) {
            return $this;
        }
        if ($name === 'partial') {
            return '<banner>Active</banner>';
        }
        if (strpos($name, 'escapeHtml') === 0) {
            return htmlspecialchars($args[0], ENT_QUOTES);
        }
        return $args[0] ?? null;
    }
}
