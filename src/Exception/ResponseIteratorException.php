<?php

namespace Symplicity\Outlook\Exception;

class ResponseIteratorException extends \Exception
{
    private $response;

    public function setResponse(array $response) : \Exception
    {
        $this->response = $response;
        return $this;
    }

    public function getResponse() : array
    {
        return $this->response;
    }
}
