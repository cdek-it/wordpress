<?php

use Cdek\CdekApi;
use Cdek\Model\Tariff;
use Cdek\WeightCalc;

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
        $this->enabled = 'yes';
        $this->init();
    }

    function init()
    {
        $this->init_form_fields();
        $this->init_settings();
        $this->title = 'CDEK Shipping';
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    function init_form_fields()
    {
        $this->form_fields = array(

            'auth_check' => array(
                'type' => 'hidden',
                'default' => 0
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
        $cdekAuth = (int)get_option('cdek_auth_check');
        $cdekShipping = WC()->shipping->load_shipping_methods()['official_cdek'];
        $cdekShippingSettings = $cdekShipping->settings;
        if ($cdekAuth) {
            $tariffList = $cdekShippingSettings['rate'];
            $city = $package["destination"]['city'];
            $state = '';
            if (array_key_exists('state', $package["destination"])) {
                $state = $package["destination"]['state'];
            }

            $totalWeight = 0;
            $lengthList = [];
            $widthList = [];
            $heightList = [];
            foreach ($package['contents'] as $productGroup) {
                $quantity = $productGroup['quantity'];
                $weight = $productGroup['data']->get_weight();
                $lengthList[] = (int)$productGroup['data']->get_length();
                $widthList[] = (int)$productGroup['data']->get_width();
                $heightList[] = (int)$productGroup['data']->get_height();
                $weightClass = new WeightCalc();
                $weight = $weightClass->getWeight($weight);
                $totalWeight += $quantity * $weight;
            }

            rsort($lengthList);
            rsort($widthList);
            rsort($heightList);

            $length = $lengthList[0];
            $width = $widthList[0];
            $height = $heightList[0];

            if ($city) {
                foreach ($tariffList as $tariff) {
                    $delivery = json_decode(cdekApi()->calculateWP($city, $state, $totalWeight, $length, $width, $height, $tariff));

                    if (property_exists($delivery, 'status') && $delivery->status === 'error') {
                        continue;
                    }

                    if (empty($delivery->errors) && $delivery->delivery_sum !== null){
                        $rate = array(
                            'id' => $this->id . '_' . $tariff,
                            'label' => 'CDEK: ' . Tariff::getTariffNameByCode($tariff) . ', (' . $delivery->period_min . '-' . $delivery->period_max . ' дней)',
                            'cost' => $delivery->total_sum,
                            'meta_data' => ['type' => Tariff::getTariffTypeToByCode($tariff)]
                        );
                        $this->add_rate($rate);
                    }
                }
            }
        }
    }
}