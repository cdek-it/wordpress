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
    use WC_Order_Item_Shipping;

    /**
     * @property int tariff
     * @property string|null $office
     * @property string $length
     * @property string $height
     * @property string $width
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
            ];
        protected const ALIASES
            = [
                MetaKeys::TARIFF_CODE => ['tariff_code'],
                MetaKeys::OFFICE_CODE => ['office_code'],
                MetaKeys::LENGTH      => ['length'],
                MetaKeys::HEIGHT      => ['height'],
                MetaKeys::WIDTH       => ['width'],
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
            $this->instanceId   = $wcShippingItem->get_data()['instance_id'];
            $this->meta         = $wcShippingItem->get_meta_data();
        }

        final public function getInstanceId(): int
        {
            return $this->instanceId;
        }

        final public function getMethod(): ShippingMethod
        {
            return ShippingMethod::factory()($this->instanceId);
        }

        /** @noinspection MissingReturnTypeInspection */
        public function __get(string $key)
        {
            if (!array_key_exists($key, self::FIELDS_MAPPER)) {
                return parent::__get($key);
            }

            return parent::__get(self::FIELDS_MAPPER[$key]);
        }

        final public function updateTotal(float $val): void
        {
            $this->originalItem->set_total($val);
        }

        final public function updateName(string $val): void
        {
            $this->originalItem->set_name($val);
        }

        final public function save(): void
        {
            $this->originalItem->set_meta_data($this->meta);
            $this->originalItem->save();
        }

        final public function clean(): void
        {
            // Doing nothing, since we don't need to clean anything
        }
    }
}
