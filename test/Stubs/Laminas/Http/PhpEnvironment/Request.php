<?php

declare(strict_types=1);

namespace Laminas\Http\PhpEnvironment;

class Request
{
    public $post = [];
    public $query = [];
    public $server = [];
    public $path = '/admin/user';
    public $method = 'GET';
    public function isPost()
    {
        return $this->method === 'POST';
    }
    public function getPost()
    {
        return $this->post;
    }
    public function getQuery($key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }
    public function getServer($key, $default = null)
    {
        return $this->server[$key] ?? $default;
    }
    public function getUri()
    {
        return $this;
    }
    public function getPath()
    {
        return $this->path;
    }
}
