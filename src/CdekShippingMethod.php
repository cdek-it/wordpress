<?php

namespace Cdek;

use Cdek\Model\Service;
use Cdek\Model\Tariff;
use WC_Shipping_Method;

class CdekShippingMethod extends WC_Shipping_Method
{
    public function __construct($instance_id = 0)
    {
        parent::__construct($instance_id);
        $this->id = 'official_cdek';
        $this->instance_id = absint($instance_id);
        $this->method_title = 'Cdek Shipping';
        $this->method_description = 'Custom Shipping Method for Cdek';
        $this->supports = array(
            'settings',
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );
        $this->enabled = 'yes';
        $this->init();
    }

    public function init()
    {
        $this->title = 'CDEK Shipping';
        $this->init_settings();
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        $this->init_form_fields();
    }

    public function init_form_fields()
    {
//        $fieldObjArray = FieldObjArray::get($this->settings);
//        foreach ($fieldObjArray as $fieldObj) {
//            $this->form_fields = array_merge($this->form_fields, $fieldObj->getFields());
//        }
        $this->form_fields = array(

            'auth_block_name' => array(
                'title' => '<h3 style="border-bottom: 2px solid; text-align: center;">Авторизация</h3>',
                'type' => 'hidden',
                'class' => 'cdek_setting_block_name'
            ),

            'client_id' => array(
                'title' => __('Идентификатор клиента', 'official_cdek'),
                'type' => 'text'
            ),

            'client_secret' => array(
                'title' => __('Секретный ключ клиента', 'official_cdek'),
                'type' => 'text'
            ),

            'seller_block_name' => array(
                'title' => '<h3 style="border-bottom: 2px solid; text-align: center;">Клиент</h3>',
                'type' => 'hidden',
                'class' => 'cdek_setting_block_name'
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

            'seller_address' => array(
                'title' => __('Адрес истинного продавца', 'official_cdek'),
                'type' => 'text',
                'description' => 'Адрес истинного продавца. Используется при печати инвойсов для отображения адреса настоящего 
                продавца товара, либо торгового названия. Для международных заказов'
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

            'delivery_block_name' => array(
                'title' => '<h3 style="border-bottom: 2px solid; text-align: center;">Доставка</h3>',
                'type' => 'hidden',
                'class' => 'cdek_delivery_block_name'
            ),

            'tariff_list' => array(
                'title' => __('Тарифы', 'official_cdek'),
                'type' => 'multiselect',
                'options' => Tariff::getTariffList(),
                'description' => "Для выбора нескольких тарифов удерживайте клавишу \"CTRL\" и левой кнопкой мыши выберите тарифы.<br>
                            Если отправка производится со склада, то рекомендуется выбирать тарифы только от склада. <br> Иначе у пользователя будет 
                            выбор тарифов \"от двери\""
            ),

            'service_list' => array(
                'title' => __('Услуги', 'official_cdek'),
                'type' => 'multiselect',
                'options' => Service::getServiceList(),
            ),

            'has_packages_mode' => array(
                'title' => __('Многоместка', 'official_cdek'),
                'type' => 'checkbox',
                'description' => "При включенном режиме 'Многоместка', на детальной странице заказа появится
                 возможность создать несколько упаковок для одного заказа и распределить товары по созданным упаковкам",
                'default' => 'no'
            ),

            'extra_day' => array(
                'title' => 'Добавленные дни',
                'type' => 'number',
                'description' => "Добавленные дни к доставке",
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

            'map_layer' => array(
                'title' => __('Слой карты', 'official_cdek'),
                'type' => 'select',
                'options' => ['OpenStreetMap', 'YandexMap']
            ),

            'yandex_map_api_key' => array(
                'type' => 'hidden',
                'placeholder' => 'Api Key'
            ),

            'map' => array(
                'type' => 'hidden',
                'title' => __('Выбрать ПВЗ на карте', 'official_cdek'),
            ),

            'pvz_code' => array(
                'type' => 'hidden',
            ),

            'pvz_address' => array(
                'type' => 'text',
                'readonly' => 'readonly',
                'description' => "Адрес отправления для тарифов \"от склада\""
            ),

            'package_setting_block_name' => array(
                'title' => '<h3 style="border-bottom: 2px solid; text-align: center;">Габариты</h3>',
                'type' => 'hidden',
                'class' => 'cdek_package_setting_block_name',
            ),

            'product_weight_default' => array(
                'title' => __('Вес одной единицы товара по умолчанию в кг', 'official_cdek'),
                'description' => "У всех товаров должен быть указан вес, 
                            если есть товары без указанного <br> веса то для таких товаров будет подставляться значение из этого поля. <br>
                            Это повлияет на точность расчета доставки. Значение по умолчанию 1 кг.",
                'type' => 'text',
                'default' => __(1, 'official_cdek')
            ),

            'product_length_default' => array(
                'title' => __('Длина товара', 'official_cdek'),
                'description' => "Длина товара по умолчанию в см",
                'type' => 'number',
                'default' => __(10, 'official_cdek')
            ),

            'product_width_default' => array(
                'title' => __('Ширина товара', 'official_cdek'),
                'description' => "Ширина товара по умолчанию в см",
                'type' => 'number',
                'default' => __(10, 'official_cdek')
            ),

            'product_height_default' => array(
                'title' => __('Высота товара', 'official_cdek'),
                'description' => "Высота товара по умолчанию в см",
                'type' => 'number',
                'default' => __(10, 'official_cdek')
            ),

            'product_package_default_toggle' => array(
                'title' => __('Габариты товара вкл/выкл', 'official_cdek'),
                'description' => 'Принудительно использовать габариты товара (длину, ширину и высоту) по умолчанию для всех товаров',
                'type' => 'checkbox',
                'default' => 'no'
            ),

            'delivery_price_block_name' => array(
                'title' => '<h3 style="border-bottom: 2px solid; text-align: center;">Cтоимость доставки</h3>',
                'type' => 'hidden',
                'class' => 'cdek_delivery_price_block_name',
            ),

            'extra_cost' => array(
                'title' => 'Цена доставки',
                'type' => 'number',
                'description' => "Добавленная цена доставки в рублях",
            ),

            'insurance' => array(
                'title' => 'Страховка',
                'type' => 'checkbox',
                'description' => "Добавлять к стоимости доставки сумму страховки. Расчитывается по сумме товаров в заказе",
            ),

            'percentprice_toggle' => array(
                'title' => 'Добавить цену доставки в процентах вкл/выкл',
                'type' => 'checkbox',
                'description' => "Использовать надбавку к цене доставки в процентах",
            ),

            'percentprice' => array(
                'title' => 'Цена доставки в процентах',
                'type' => 'number',
                'description' => "К примеру, 130% означает что к стоимости доставки прибавится 30 процентов от расчетной стоимости.
                Значение не может быть меньше 100%",
            ),

            'fixprice_toggle' => array(
                'title' => 'Фикс цена доставки вкл/выкл',
                'type' => 'checkbox',
                'description' => "Использовать фиксированную цену доставки в рублях",
            ),

            'fixprice' => array(
                'title' => 'Фиксированная цена',
                'type' => 'number',
                'description' => "Фиксированная цена доставки в рублях",
            ),

            'stepprice_toggle' => array(
                'title' => 'Бесплатная доставка от суммы заказа вкл/выкл',
                'type' => 'checkbox',
                'description' => "Бесплатная доставка от суммы заказа указаной в поле 'Бесплатная доставка от'",
            ),

            'stepprice' => array(
                'title' => 'Бесплатная доставка от',
                'type' => 'number',
                'description' => "Бесплатная доставка от суммы в рублях",
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
        $deliveryCalc = new DeliveryCalc();
        if ($deliveryCalc->calculate($package, $this->id)) {
            foreach ($deliveryCalc->rates as $rate) {
                $this->add_rate($rate);
            }
        }
    }

}