<?php

namespace Cdek\UI;

use Cdek\CdekApi;
use Cdek\Config;
use Cdek\Helpers\WeightCalc;
use Cdek\MetaKeys;
use Exception;
use WC_Order_Item_Shipping;

class AdminShippingFields
{
    public function __invoke($item_id, $item): void
    {
        if (!$item instanceof WC_Order_Item_Shipping || $item->get_method_id() !== Config::DELIVERY_NAME) {
            return;
        }

        foreach ($item->get_meta_data() as $value) {
            $meta = $value->get_data();

            switch ($meta['key']) {
                case 'weight (g)':
                case 'weight (oz)':
                case 'weight (lbs)':
                case 'weight (kg)':
                    self::renderWeight($meta['value']);
                    break;
                case 'weight':
                case MetaKeys::WEIGHT:
                    self::renderWeight(WeightCalc::getWeightInWcMeasurement($meta['value']));
                    break;
                case MetaKeys::LENGTH:
                case 'length':
                    self::renderLength($meta['value']);
                    break;
                case MetaKeys::WIDTH:
                case 'width':
                    self::renderWidth($meta['value']);
                    break;
                case MetaKeys::HEIGHT:
                case 'height':
                    self::renderHeight($meta['value']);
                    break;
                case 'pvz':
                    self::renderOffice($meta['value']);
                    break;
                case MetaKeys::OFFICE_CODE:
                    try {
                        $officeAddress = json_decode((new CdekApi)->getOffices(['code' => $meta['value']])['body'],
                                                     true, 512,
                                                     JSON_THROW_ON_ERROR);

                        self::renderOffice(sprintf('%s (%s)', $meta['value'],
                                                   $officeAddress[0]['location']['address']));
                    } catch (Exception $exception) {
                        self::renderOffice('Недоступен для заказа');
                    }
                    break;
                case 'tariff_code':
                case MetaKeys::TARIFF_CODE:
                    self::renderTariff($meta['value']);
                    break;
                default:
            }
        }
    }

    private static function renderWeight($value): void
    {
        $measurement = get_option('woocommerce_weight_unit');
        echo "<div>Вес: $value$measurement</div>";
    }

    private static function renderLength(string $length): void
    {
        echo "<div>Длина: {$length}см</div>";
    }

    private static function renderWidth(string $length): void
    {
        echo "<div>Ширина: {$length}см</div>";
    }

    private static function renderHeight(string $length): void
    {
        echo "<div>Высота: {$length}см</div>";
    }

    private static function renderOffice($value): void
    {
        echo "<div>Выбранный ПВЗ: $value</div>";
    }

    private static function renderTariff($tariffCode): void
    {
        echo "<div>Код тарифа: $tariffCode</div>";
    }
}
