<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Model {

    use Cdek\Config;
    use Cdek\Contracts\MetaModelContract;
    use Cdek\MetaKeys;
    use Cdek\ShippingMethod;
    use InvalidArgumentException;
    use WC_Meta_Data;
    use WC_Order_Item_Shipping;

    /**
     * @property string tariff
     * @property string|null $office
     * @property string $length
     * @property string $height
     * @property string $width
     * @property string $weight
     */
    class ShippingItem extends MetaModelContract
    {
        private const FIELDS_MAPPER
            = [
                'tariff' => MetaKeys::TARIFF_CODE,
                'office' => MetaKeys::OFFICE_CODE,
                'length' => MetaKeys::LENGTH,
                'height' => MetaKeys::HEIGHT,
                'width'  => MetaKeys::WIDTH,
                'weight' => MetaKeys::WEIGHT,
            ];
        protected const ALIASES
            = [
                MetaKeys::TARIFF_CODE => ['tariff_code'],
                MetaKeys::OFFICE_CODE => ['office_code'],
                MetaKeys::LENGTH      => ['length'],
                MetaKeys::HEIGHT      => ['height'],
                MetaKeys::WIDTH       => ['width'],
                MetaKeys::WEIGHT      => ['weight'],
            ];
        private int $instanceId;
        private WC_Order_Item_Shipping $originalItem;

        /**
         * @throws InvalidArgumentException
         */
        public function __construct(WC_Order_Item_Shipping $wcShippingItem)
        {
            if ($wcShippingItem->get_method_id() !== Config::DELIVERY_NAME) {
                throw new InvalidArgumentException('WC_Order_Item_Shipping that belongs to plugin expected');
            }

            $this->originalItem = $wcShippingItem;
            $this->instanceId   = (int)$wcShippingItem->get_data()['instance_id'];
            $this->meta         = [];

            foreach ($wcShippingItem->get_meta_data() as $meta) {
                assert($meta instanceof WC_Meta_Data);
                $data                     = $meta->get_data();
                $this->meta[$data['key']] = $data['value'];
            }
        }

        /** @noinspection MissingReturnTypeInspection */
        public function __get(string $key)
        {
            if (!array_key_exists($key, self::FIELDS_MAPPER)) {
                return parent::__get($key);
            }

            return parent::__get(self::FIELDS_MAPPER[$key]);
        }

        public function __set(string $key, $value): void
        {
            if (!array_key_exists($key, self::FIELDS_MAPPER)) {
                parent::__set($key, $value);
            }

            parent::__set(self::FIELDS_MAPPER[$key], $value);
        }

        final public function clean(): void
        {
            // Doing nothing, since we don't need to clean anything
        }

        final public function getInstanceId(): int
        {
            return $this->instanceId;
        }

        final public function getMethod(): ShippingMethod
        {
            return ShippingMethod::factory($this->instanceId);
        }

        final public function save(): void
        {
            foreach ($this->dirty as $key) {
                $this->originalItem->add_meta_data($key, $this->meta[$key], true);
            }
            $this->dirty = [];
            $this->originalItem->save();
        }

        final public function updateName(string $val): void
        {
            $this->originalItem->set_name($val);
        }

        final public function updateTotal(float $val): void
        {
            $this->originalItem->set_total($val);
        }
    }
}
