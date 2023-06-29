<?php

namespace Cdek\Model;

class Validate
{
    public $state;
    public $message;

    public function __construct($state, $message = '')
    {
        $this->state = $state;
        $this->message = $message;
    }

    public function response()
    {
        return json_encode(['state' => $this->state, 'message' => $this->message]);
    }
}