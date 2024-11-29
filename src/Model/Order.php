<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Model {

    use Cdek\CdekApi;
    use Cdek\Contracts\MetaModelContract;
    use Cdek\Exceptions\OrderNotFoundException;
    use DateTimeImmutable;
    use DateTimeInterface;
    use InvalidArgumentException;
    use WC_Order;
    use WC_Order_Item_Product;
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
     */
    class Order extends MetaModelContract
    {
        private const PROXY_FIELDS
            = [
                'id',
                'currency',
                'payment_method',
                'billing_email',
                'shipping_total',
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
        private const SHIPPING_ALLOWED_PRODUCT_TYPES = ['variation', 'simple'];
        private WC_Order $order;
        private ?ShippingItem $shipping = null;
        private ?bool $locked = null;

        /**
         * @param  WC_Order|WP_Post|int  $order
         *
         * @noinspection MissingParameterTypeDeclarationInspection
         * @throws \Cdek\Exceptions\OrderNotFoundException
         */
        public function __construct($order)
        {
            if ($order instanceof WC_Order) {
                $this->order = $order;
            } else {
                $orderSearch = wc_get_order($order);

                if ($orderSearch === false) {
                    throw new OrderNotFoundException;
                }

                $this->order = $orderSearch;
            }

            $this->meta = $this->order->get_meta(self::META_KEY) ?: [];
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

        final public function clean(): void
        {
            unset(
                $this->meta['number'], $this->meta['uuid'], $this->meta['order_uuid'], $this->meta['order_number'], $this->meta['cdek_order_uuid'], $this->meta['cdek_order_waybill'],
            );

            $this->order->update_meta_data(self::META_KEY, $this->meta);
            $this->order->save();
        }

        final public function save(): void
        {
            $this->order->update_meta_data(self::META_KEY, $this->meta);
            $this->order->save();
        }

        final public function getIntake(): Intake
        {
            return new Intake($this->order);
        }

        /**
         * @param  bool  $forShipping
         *
         * @return WC_Order_Item_Product[]
         */
        final public function getItems(bool $forShipping = true): array
        {
            $items = $this->order->get_items('line_item');

            return $forShipping ? array_filter($items, static fn(WC_Order_Item_Product $e)
                => in_array(
                $e->get_product()->get_type(),
                self::SHIPPING_ALLOWED_PRODUCT_TYPES,
                true,
            )) : $items;
        }

        final public function getShipping(): ?ShippingItem
        {
            if ($this->shipping !== null) {
                return $this->shipping;
            }

            $shippingMethods = $this->order->get_shipping_methods();

            foreach ($shippingMethods as $method) {
                try {
                    $this->shipping = new ShippingItem($method);

                    return $this->shipping;
                } catch (InvalidArgumentException $e) {
                    continue;
                }
            }

            return null;
        }

        final public function isLocked(): ?bool
        {
            return $this->locked;
        }

        final public function isPaid(): bool
        {
            return $this->order->is_paid();
        }

        /**
         * @throws \Cdek\Exceptions\OrderNotFoundException
         * @throws \Cdek\Exceptions\External\ApiException
         * @throws \Cdek\Exceptions\External\UnparsableAnswerException
         * @throws \Cdek\Exceptions\External\LegacyAuthException
         */
        final public function loadLegacyStatuses(?array $statuses = null): array
        {
            if (empty($this->uuid)) {
                return [];
            }

            if ($statuses === null) {
                $orderInfo = (new CdekApi)->orderGet($this->uuid);
                if ($orderInfo->entity() === null) {
                    throw new OrderNotFoundException;
                }

                $statuses = $orderInfo->entity()['statuses'];

                if (empty($this->number)) {
                    $this->number = $orderInfo->entity()['cdek_number'];
                    $this->save();
                }
            }

            $statuses = array_map(static fn(array $st)
                => [
                'time' => DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $st['date_time']),
                'name' => $st['name'],
                'code' => $st['code'],
            ], $statuses);

            $this->locked = $statuses[0]['code'] !== 'CREATED' && $statuses[0]['code'] !== 'INVALID';

            return $statuses;
        }

        /**
         * @noinspection MissingReturnTypeInspection
         * TODO Выпилить после перехода сохранения на Fieldset-ы
         */
        final public function meta(string $key)
        {
            return $this->order->get_meta($key);
        }

        final public function shouldBePaidUponDelivery(): bool
        {
            return $this->payment_method === 'cod';
        }
    }
}
