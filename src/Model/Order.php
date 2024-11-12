<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Model {

    use Cdek\CdekApi;
    use Cdek\Contracts\MetaModelContract;
    use DateTime;
    use InvalidArgumentException;
    use RuntimeException;
    use WC_Order;
    use WP_Post;

    /**
     * @property string|null $uuid
     * @property string|null $number
     *
     * @property-read int $tariff_id
     * @property-read string $pvz_code
     *
     * @property-read string $country
     * @property-read string $city
     * @property-read string $postcode
     * @property-read string $address_1
     * @property-read string $phone
     * @property-read string $first_name
     * @property-read string $last_name
     *
     * @property int $id
     * @property string $currency
     * @property string $payment_method
     * @property float $shipping_total
     * @property string $billing_email
     * @property \WC_Order_Item[] $items
     */
    class Order extends MetaModelContract
    {
        private const PROXY_FIELDS
            = [
                'id',
                'currency',
                'payment_method',
                'items',
                'billing_email',
            ];
        private const CHECKOUT_FIELDS
            = [
                'country',
                'city',
                'postcode',
                'address_1',
                'phone',
                'first_name',
                'last_name',
            ];
        protected const ALIASES
            = [
                'uuid'   => ['order_uuid'],
                'number' => ['order_number'],
            ];
        private const META_KEY = 'order_data';
        private WC_Order $order;
        private array $meta;

        /**
         * @param  WC_Order|WP_Post|int  $order
         *
         * @noinspection MissingParameterTypeDeclarationInspection
         */
        public function __construct($order)
        {
            $this->order = $order instanceof WC_Order ? $order : wc_get_order($order);
            $this->meta  = $this->order->get_meta(self::META_KEY) ?: [];
        }

        public static function getMetaByOrderId(int $orderId): array
        {
            return wc_get_order($orderId)->get_meta(self::META_KEY) ?: [];
        }

        public static function isLockedByStatuses(array $statuses): bool
        {
            return !($statuses[0]['code'] !== 'CREATED' && $statuses[0]['code'] !== 'INVALID');
        }

        final public function getShipping(): ?ShippingItem
        {
            $shippingMethods = $this->order->get_shipping_methods();

            foreach ($shippingMethods as $method) {
                try {
                    return new ShippingItem($method);
                } catch (InvalidArgumentException $e) {
                    continue;
                }
            }

            return null;
        }

        final public function getIntake(): Intake
        {
            return new Intake($this->order);
        }

        /** @noinspection MissingReturnTypeInspection */
        public function __get(string $key)
        {
            if (in_array($key, self::PROXY_FIELDS, true)) {
                return call_user_func([$this->order, "get_$key"]) ?: null;
            }

            if (in_array($key, self::CHECKOUT_FIELDS, true)) {
                $val = call_user_func([$this->order, "get_shipping_$key"]) ?:
                    call_user_func([$this->order, "get_billing_$key"]);

                return !empty($val) ? trim($val) : null;
            }

            return parent::__get($key);
        }

        public function __set(string $key, $value): void
        {
            if (in_array($key, self::PROXY_FIELDS, true)) {
                $this->order->{"set_$key"}($value);
            }

            parent::__set($key, $value);
        }

        final public function isPaid(): bool
        {
            return $this->order->is_paid();
        }

        final public function shouldBePaidUponDelivery(): bool
        {
            return $this->payment_method === 'cod';
        }

        final public function clean(): void
        {
            unset($this->meta['order_uuid'], $this->meta['order_number'], $this->meta['cdek_order_uuid'], $this->meta['cdek_order_waybill']);

            $this->order->update_meta_data(self::META_KEY, $this->meta);
            $this->order->save();
        }

        final public function save(): void
        {
            $this->order->update_meta_data(self::META_KEY, $this->meta);
            $this->order->save();
        }

        // TODO Выпилить после перехода сохранения на Fieldset-ы
        /** @noinspection MissingReturnTypeInspection */
        final public function meta(string $key)
        {
            return $this->order->get_meta($key);
        }

        final public function loadLegacyStatuses(): array
        {
            if (empty($this->uuid)) {
                throw new RuntimeException('[CDEKDelivery] Не найден UUID заказа.');
            }

            $orderInfo = (new CdekApi)->orderGet($this->uuid);
            if ($orderInfo->entity() === null) {
                throw new RuntimeException('[CDEKDelivery] Статусы не найдены. Заказ не найден.');
            }

            return array_map(static fn(array $st)
                => [
                'time' => DateTime::createFromFormat('Y-m-d\TH:i:sO', $st['date_time']),
                'name' => $st['name'],
                'code' => $st['code'],
            ], $orderInfo->entity()['statuses']);
        }
    }
}
