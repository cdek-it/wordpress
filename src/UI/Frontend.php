<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}


namespace Cdek\UI {

    use Cdek\Helper;
    use Cdek\Traits\CanBeCreated;

    class Frontend
    {
        use CanBeCreated;

        public static function registerScripts(): void
        {
            if (!is_checkout()) {
                return;
            }

            Helper::enqueueScript('cdek-map', 'cdek-checkout-map', true);
        }

        public function __invoke(): void
        {
            add_action('wp_enqueue_scripts', [__CLASS__, 'registerScripts']);
        }
    }
}
