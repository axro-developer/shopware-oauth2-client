<?php

namespace AxroShopware\Exception;

use Exception;

class AccessTokenException extends Exception
{
    private mixed $request;
    private mixed $response;

    public function __construct(
        $message = null,
        $request = null,
        $response = null,
    ) {
        $message = $message ?: 'Invalid or missing AccessToken!';

        parent::__construct($message);
        $this->message = $message;
        $this->request = $request;
        $this->response = $response;
    }

    public function getRequest(): mixed
    {
        return $this->request;
    }

    public function getResponse(): mixed
    {
        return $this->response;
    }
}