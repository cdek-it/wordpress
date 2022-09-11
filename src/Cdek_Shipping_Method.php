<?php

namespace Cdek;

use Cdek\Model\Tariff;
use WC_Shipping_Method;

class CdekShippingMethod extends WC_Shipping_Method
{
    public function __construct($instance_id = 0)
    {
        $this->id = 'cdek';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Cdek Shipping', 'cdek');
        $this->method_description = __('Custom Shipping Method for Cdek', 'cdek');
        $this->supports = array(
            'settings',
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );
        $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
        $this->init();
    }

    function init()
    {
        $this->init_form_fields();
        $this->init_settings();
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    function init_form_fields()
    {

        $this->form_fields = array(

            'enabled' => array(
                'title' => __('Enable', 'cdek'),
                'type' => 'checkbox',
                'description' => __('Enable this shipping.', 'cdek'),
                'default' => 'yes'
            ),

            'grant_type' => array(
                'title' => __('Тип аутентификации', 'cdek'),
                'type' => 'text',
                'default' => __('client_credentials', 'cdek')
            ),

            'client_id' => array(
                'title' => __('Идентификатор клиента', 'cdek'),
                'type' => 'text',
                'default' => __('EMscd6r9JnFiQ3bLoyjJY6eM78JrJceI', 'cdek')
            ),

            'client_secret' => array(
                'title' => __('Секретный ключ клиента', 'cdek'),
                'type' => 'text',
                'default' => __('PjLZkKBHEiLK3YsjtNrt3TGNG0ahs3kG', 'cdek')
            ),

            'seller_name' => array(
                'title' => __('ФИО', 'cdek'),
                'type' => 'text',
                'default' => __('Клементьев Илья', 'cdek')
            ),

            'seller_phone' => array(
                'title' => __('Номер телефона', 'cdek'),
                'type' => 'text',
                'default' => __('+79969633817', 'cdek')
            ),

            'rate' => array(
                'title' => __('Тарифы', 'cdek'),
                'type' => 'multiselect',
                'options' => Tariff::getTariffList(),
                'description' => "Для выбора нескольких тарифов удерживайте клавишу \"CTRL\" и левой кнопкой мыши выберите тарифы.<br>
                            Если отправка производится со склада, то рекомендуется выбирать тарифы только от склада. <br> Иначе у пользователя будет 
                            выбор тарифов \"от двери\""
            ),


            'default_weight' => array(
                'title' => __('Вес одной единицы товара по умолчанию в кг', 'cdek'),
                'description' => "У всех товаров должен быть указан вес, 
                            если есть товары без указанного <br> веса то для таких товаров будет подставляться значение из этого поля. <br>
                            Это повлияет на точность расчета доставки. Значение по умолчанию 1 кг.",
                'type' => 'text',
                'default' => __(1, 'cdek')
            ),

            'tiles' => array(
                'title' => __('Слой карты', 'cdek'),
                'type' => 'select',
                'options' => ['OpenStreetMap', 'YandexMap']
            ),

            'apikey' => array(
                'type' => 'hidden',
                'placeholder' => 'Api Key',
                'default' => __('', 'cdek')
            ),

            'city' => array(
                'title' => __('Город отправления', 'cdek'),
                'type' => 'text',
                'default' => __('Москва', 'cdek')
            ),

            'street' => array(
                'title' => __('Адрес', 'cdek'),
                'type' => 'text',
                'default' => __('Ленина 21 42', 'cdek'),
                'description' => "Адрес отправления для тарифов \"от двери\""
            ),

            'map' => array(
                'type' => 'hidden',
                'title' => __('Выбрать ПВЗ на карте', 'cdek'),
            ),

            'pvz_info' => array(
                'type' => 'text',
                'readonly' => 'readonly',
                'description' => "Адрес отправления для тарифов \"от склада\""
            ),

            'pvz_code' => array(
                'type' => 'hidden',
            ),

            'city_code_value' => array(
                'type' => 'text',
                'css' => 'display: none;',
                'default' => __('44', 'cdek')
            ),

        );

    }

    public function calculate_shipping($package = [])
    {
        $cdekShipping = WC()->shipping->load_shipping_methods()['cdek'];
        $cdekShippingSettings = $cdekShipping->settings;
        $tariffList = $cdekShippingSettings['rate'];
        $city = $package["destination"]['city'];
        $postcode = $package["destination"]['postcode'];

        $totalWeight = 0;
        foreach ($package['contents'] as $productGroup) {
            $quantity = $productGroup['quantity'];
            $weight = $productGroup['data']->get_weight();
            if ((int)$weight === 0) {
                $weight = $cdekShippingSettings['default_weight'];
            }
            $totalWeight += $quantity * (int) $weight;
        }

        if ($city) {
            foreach ($tariffList as $tariff) {
                $delivery = json_decode(cdekApi()->calculateWP($city, $postcode, $totalWeight, $tariff));

                if (property_exists($delivery, 'status') && $delivery->status === 'error') {
                    continue;
                }

                if (empty($delivery->errors) && $delivery->delivery_sum !== null){
                    $rate = array(
                        'id' => $this->id . '_' . $tariff,
                        'label' => 'CDEK: ' . Tariff::getTariffNameByCode($tariff) . ', (' . $delivery->period_min . '-' . $delivery->period_max . ' дней)',
                        'cost' => $delivery->delivery_sum,
                        'meta_data' => ['type' => Tariff::getTariffTypeToByCode($tariff)],
                        'className' => 'asdasd',
                        'class' => 'asdasd',
                    );
                    $this->add_rate($rate);
                }
            }
        }
    }
}