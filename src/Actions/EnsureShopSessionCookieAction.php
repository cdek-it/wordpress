<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use WC_Session_Handler;

    class EnsureShopSessionCookieAction
    {
        /**
         * WooCommerce выставляет сессионную куку только в ответ на первую мутацию корзины
         * (первый add-to-cart), а не заранее на просмотре страницы - это создаёт окно гонки:
         * несколько быстрых кликов "в корзину" до того, как браузер применит Set-Cookie из
         * ответа на первый клик, порождают отдельные гостевые сессии и 409
         * `woocommerce_rest_cart_invalid_key` на последующих запросах. Форсируем куку заранее,
         * только на страницах магазина - чтобы не сажать full-page кеш остального сайта
         * на Set-Cookie у гостей.
         */
        public function __invoke(): void
        {
            if (!is_shop() && !is_product() && !is_product_category() && !is_product_tag()) {
                return;
            }

            $session = WC()->session;

            if (!$session instanceof WC_Session_Handler || $session->has_session()) {
                return;
            }

            $session->set_customer_session_cookie(true);
        }
    }
}
