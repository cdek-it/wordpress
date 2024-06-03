<?php

namespace Cdek\Contracts;

abstract class TaskContract
{
    abstract public static function getName(): string;
    abstract public static function init($metaData = []);
}
