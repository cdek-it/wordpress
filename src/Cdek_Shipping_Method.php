<?php

use Cdek\Model\Tariff;

class CdekShippingMethod extends WC_Shipping_Method
{
    public function __construct($instance_id = 0)
    {
        $this->id = 'official_cdek';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Cdek Shipping', 'official_cdek');
        $this->method_description = __('Custom Shipping Method for Cdek', 'official_cdek');
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
                'title' => __('Enable', 'official_cdek'),
                'type' => 'checkbox',
                'description' => __('Enable this shipping.', 'official_cdek'),
                'default' => 'yes'
            ),

            'grant_type' => array(
                'title' => __('Тип аутентификации', 'official_cdek'),
                'type' => 'text',
                'default' => __('client_credentials', 'official_cdek')
            ),

            'client_id' => array(
                'title' => __('Идентификатор клиента', 'official_cdek'),
                'type' => 'text'
            ),

            'client_secret' => array(
                'title' => __('Секретный ключ клиента', 'official_cdek'),
                'type' => 'text'
            ),

            'seller_name' => array(
                'title' => __('ФИО', 'official_cdek'),
                'type' => 'text'
            ),

            'seller_phone' => array(
                'title' => __('Телефон', 'official_cdek'),
                'type' => 'text',
                'description' => 'Должен передаваться в международном формате: код страны (для России +7) и сам номер (10 и более цифр)'
            ),

            'shipper_name' => array(
                'title' => __('Грузоотправитель', 'official_cdek'),
                'type' => 'text',
                'description' => 'Название компании грузоотправителя для международных заказов'
            ),

            'shipper_address' => array(
                'title' => __('Адрес грузоотправителя', 'official_cdek'),
                'type' => 'text',
                'description' => 'Адрес компании грузоотправителя для международных заказов'
            ),

            'seller_address' => array(
                'title' => __('Адрес истинного продавца', 'official_cdek'),
                'type' => 'text',
                'description' => 'Адрес истинного продавца. Используется при печати инвойсов для отображения адреса настоящего 
                продавца товара, либо торгового названия. Для международных заказов'
            ),

            'rate' => array(
                'title' => __('Тарифы', 'official_cdek'),
                'type' => 'multiselect',
                'options' => Tariff::getTariffList(),
                'description' => "Для выбора нескольких тарифов удерживайте клавишу \"CTRL\" и левой кнопкой мыши выберите тарифы.<br>
                            Если отправка производится со склада, то рекомендуется выбирать тарифы только от склада. <br> Иначе у пользователя будет 
                            выбор тарифов \"от двери\""
            ),

            'default_weight' => array(
                'title' => __('Вес одной единицы товара по умолчанию в кг', 'official_cdek'),
                'description' => "У всех товаров должен быть указан вес, 
                            если есть товары без указанного <br> веса то для таких товаров будет подставляться значение из этого поля. <br>
                            Это повлияет на точность расчета доставки. Значение по умолчанию 1 кг.",
                'type' => 'text',
                'default' => __(1, 'official_cdek')
            ),

            'tiles' => array(
                'title' => __('Слой карты', 'official_cdek'),
                'type' => 'select',
                'options' => ['OpenStreetMap', 'YandexMap']
            ),

            'apikey' => array(
                'type' => 'hidden',
                'placeholder' => 'Api Key'
            ),

            'has_packages' => array(
                'title' => __('Многоместка', 'official_cdek'),
                'type' => 'checkbox',
                'description' => "При включенном режиме 'Многоместка', на детальной странице заказа появится
                 возможность создать несколько упаковок для одного заказа и распределить товары по созданным упаковкам",
                'default' => 'no'
            ),

            'city' => array(
                'title' => __('Город отправления', 'official_cdek'),
                'type' => 'text',
                'default' => __('Москва', 'official_cdek')
            ),

            'street' => array(
                'title' => __('Адрес', 'official_cdek'),
                'type' => 'text',
                'description' => "Адрес отправления для тарифов \"от двери\""
            ),

            'map' => array(
                'type' => 'hidden',
                'title' => __('Выбрать ПВЗ на карте', 'official_cdek'),
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
                'default' => __('44', 'official_cdek')
            ),

        );

    }

    public function calculate_shipping($package = [])
    {
        $cdekShipping = WC()->shipping->load_shipping_methods()['official_cdek'];
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
                        'meta_data' => ['type' => Tariff::getTariffTypeToByCode($tariff)]
                    );
                    $this->add_rate($rate);
                }
            }
        }
    }
}