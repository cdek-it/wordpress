<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Model {

    use Cdek\Contracts\MetaModelContract;
    use WC_Order;

    /**
     * @property string|null number
     * @property string|null uuid
     */
    class Intake extends MetaModelContract
    {
        private const META_KEY = 'courier_data';
        protected const ALIASES
            = [
                'number' => ['courier_number'],
                'uuid'   => ['courier_uuid'],
            ];
        private WC_Order $order;

        /**
         * @param  WC_Order|int  $order
         *
         * @noinspection MissingParameterTypeDeclarationInspection
         */
        public function __construct($order)
        {
            $this->order = $order instanceof WC_Order ? $order : wc_get_order($order);
            $this->meta  = $this->order->get_meta(self::META_KEY) ?: [];
        }

        final public function clean(): void
        {
            $this->order->delete_meta_data(self::META_KEY);
            $this->order->save();
        }

        final public function save(): void
        {
            $this->order->delete_meta_data(self::META_KEY);
            $this->order->add_meta_data(self::META_KEY, $this->meta, true);
            $this->order->save();
        }
    }
}
