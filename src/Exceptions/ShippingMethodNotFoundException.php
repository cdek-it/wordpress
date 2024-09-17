<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Exceptions {

    use Exception;

    class ShippingMethodNotFoundException extends Exception {}
}
