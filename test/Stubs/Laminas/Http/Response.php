<?php

declare(strict_types=1);

namespace Laminas\Http;

class Response
{
    const STATUS_CODE_400 = 400;
    const STATUS_CODE_403 = 403;
    const STATUS_CODE_404 = 404;
    const STATUS_CODE_405 = 405;
    const STATUS_CODE_409 = 409;
    const STATUS_CODE_500 = 500;
    public $status = 200;
    public $content = '';
    public $headers = [];
    public function setStatusCode($status)
    {
        $this->status = $status;
        return $this;
    }
    public function setContent($content)
    {
        $this->content = $content;
        return $this;
    }
    public function getHeaders()
    {
        return $this;
    }
    public function addHeaderLine($name, $value)
    {
        $this->headers[$name] = $value;
    }
}
