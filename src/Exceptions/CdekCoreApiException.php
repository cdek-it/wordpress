<?php

namespace Cdek\Exceptions;
class CdekCoreApiException extends \Cdek\Exceptions\CdekException
{
    protected bool $isSchedule = true;
}
