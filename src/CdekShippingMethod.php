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
        $this->supports = [
            'settings',
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        ];
        $this->enabled = 'yes';
        $this->init();
    }

    public function init()
    {
        $this->title = 'CDEK Shipping';
        $this->init_settings();
        add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
        $this->init_form_fields();
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'auth_block_name' => [
                'title' => '<h3 style="text-align: center;">Авторизация</h3>',
                'type' => 'title',
                'class' => 'cdek_setting_block_name'
            ], 'client_id' => [
                'title' => __('Идентификатор', 'official_cdek'),
                'type' => 'text',
                'custom_attributes' => [
                    'required' => true,
                ]
            ], 'client_secret' => [
                'title' => __('Секретный ключ', 'official_cdek'),
                'type' => 'text',
                'custom_attributes' => [
                    'required' => true,
                ]
            ], 'seller_block_name' => [
                'title' => '<h3 style="text-align: center;">Клиент</h3>',
                'type' => 'title',
                'class' => 'cdek_setting_block_name'
            ], 'seller_name' => [
                'title' => __('ФИО', 'official_cdek'),
                'type' => 'text',
                'custom_attributes' => [
                    'required' => true,
                ]
            ], 'seller_phone' => [
                'title' => __('Телефон', 'official_cdek'),
                'type' => 'text',
                'desc_tip' => true,
                'description' => 'Должен передаваться в международном формате: код страны (для России +7) и сам номер (10 и более цифр)',
                'custom_attributes' => [
                    'required' => true,
                ]
            ], 'international_title' => [
                'title' => 'Международные заказы',
                'type' => 'title',
            ], 'international_mode' => [
                'title' => __('Включить режим международных заказов', 'official_cdek'),
                'type' => 'checkbox',
                'desc_tip' => true,
                'description' => "При включенном режиме международных заказов, на странице чекаута появятся дополнительные поля:
                серия паспорта, номер паспорта, дата выдачи, отдел, ИНН, дата рождения.",
                'default' => 'no'
            ], 'seller_address' => [
                'title' => __('Адрес истинного продавца', 'official_cdek'),
                'type' => 'text',
                'desc_tip' => true,
                'description' => 'Адрес истинного продавца. Используется при печати инвойсов для отображения адреса настоящего 
                продавца товара, либо торгового названия. Для международных заказов'
            ], 'shipper_name' => [
                'title' => __('Грузоотправитель', 'official_cdek'),
                'type' => 'text',
                'desc_tip' => true,
                'description' => 'Название компании грузоотправителя для международных заказов'
            ], 'shipper_address' => [
                'title' => __('Адрес грузоотправителя', 'official_cdek'),
                'type' => 'text',
                'desc_tip' => true,
                'description' => 'Адрес компании грузоотправителя для международных заказов'
            ], 'passport_series' => [
                'title' => __('Серия паспорта', 'official_cdek'),
                'type' => 'text',
                'desc_tip' => true,
                'description' => __('ограничение 4 символа', 'official_cdek'),
                'custom_attributes' => [
                    'maxlength' => 4,
                    'pattern' => '\d*'
                ],
            ], 'passport_number' => [
                'title' => __('Номер паспорта', 'official_cdek'),
                'type' => 'text',
                'desc_tip' => true,
                'description' => __('ограничение 6 символов', 'official_cdek'),
                'custom_attributes' => [
                    'maxlength' => 6,
                    'pattern' => '\d*'
                ],
            ], 'passport_date_of_issue' => [
                'title' => __('Дата выдачи паспорта', 'official_cdek'),
                'type' => 'date',
                'date_format' => 'd.m.Y'
            ], 'passport_organization' => [
                'title' => __('Орган выдачи паспорта', 'official_cdek'),
                'type' => 'text',
            ], 'tin' => [
                'title' => __('ИНН', 'official_cdek'),
                'type' => 'text',
                'desc_tip' => true,
                'description' => __('Ограничение 12 символов', 'official_cdek'),
                'custom_attributes' => [
                    'maxlength' => 12,
                    'pattern' => '\d*'
                ],
            ], 'passport_date_of_birth' => [
                'title' => __('Дата рождения', 'official_cdek'),
                'type' => 'date',
                'date_format' => 'd.m.Y'
            ], 'delivery_block_name' => [
                'title' => '<h3 style="text-align: center;">Доставка</h3>',
                'type' => 'title',
                'class' => 'cdek_delivery_block_name'
            ], 'tariff_list' => [
                'title' => __('Тарифы', 'official_cdek'),
                'type' => 'multiselect',
                'desc_tip' => true,
                'options' => Tariff::getTariffList(),
                'description' => "Для выбора нескольких тарифов удерживайте клавишу \"CTRL\" и левой кнопкой мыши выберите тарифы.",
                'css' => 'height: 400px;'
            ], 'tariff_name' => [
                'title' => __('Изменить название тарифа', 'official_cdek'),
                'type' => 'text',
                'description' => "В списке тарифов в поле \"Тарифы\" в скобках указан код тарифа. Для изменения названия тарифа
                в поле добавляется запись в формате код-название, для множественного изменения, тарифы отделяются точкой с запятой, например
                запись которая изменит название 136 и 137 тарифа выглядит так: <b>136-Доставка до пвз;137-Доставка курьером</b> <br>
                Если значение не задано то названия тарифов будут стандартными."
            ],
//            'service_list' => [
//                'title' => __('Услуги', 'official_cdek'),
//                'type' => 'multiselect',
//                'options' => Service::getServiceList(),
//            ],
            'has_packages_mode' => [
                'title' => __('Многоместка', 'official_cdek'),
                'type' => 'checkbox',
                'desc_tip' => true,
                'description' => "При включенном режиме 'Многоместка', на детальной странице заказа появится
                 возможность создать несколько упаковок для одного заказа и распределить товары по созданным упаковкам",
                'default' => 'no'
            ], 'extra_day' => [
                'title' => 'Доп. дни к доставке',
                'type' => 'number',
                'desc_tip' => true,
                'description' => "Колличество дней будет добавлено к расчетному времени доставки",
                'default' => 0,
                'custom_attributes' => [
                    'min' => 0,
                    'step' => 1
                ]
            ], 'city' => [
                'title' => __('Город отправления', 'official_cdek'),
                'type' => 'text',
                'default' => __('Москва', 'official_cdek'),
                'custom_attributes' => [
                    'required' => true,
                ]
            ], 'street' => [
                'title' => __('Адрес', 'official_cdek'),
                'type' => 'text',
                'desc_tip' => true,
                'description' => "Адрес отправления для тарифов \"от двери\""
            ], 'map_layer' => [
                'title' => __('Слой карты', 'official_cdek'),
                'type' => 'select',
                'options' => ['OpenStreetMap', 'YandexMap']
            ], 'yandex_map_api_key' => [
                'type' => 'hidden',
                'placeholder' => 'Api Key'
            ], 'map' => [
                'type' => 'hidden',
                'title' => __('Выбрать ПВЗ на карте', 'official_cdek'),
            ], 'pvz_code' => [
                'type' => 'hidden',
            ], 'pvz_address' => [
                'type' => 'text',
                'readonly' => 'readonly',
                'desc_tip' => true,
                'description' => "Адрес отправления для тарифов \"от склада\""
            ], 'package_setting_block_name' => [
                'title' => '<h3 style="text-align: center;">Габариты</h3>',
                'type' => 'title',
                'class' => 'cdek_package_setting_block_name',
            ], 'product_weight_default' => [
                'title' => __('Вес одной единицы товара по умолчанию в кг', 'official_cdek'),
                'desc_tip' => true,
                'description' => "У всех товаров должен быть указан вес, 
                            если есть товары без указанного <br> веса то для таких товаров будет подставляться значение из этого поля. <br>
                            Это повлияет на точность расчета доставки. Значение по умолчанию 1 кг.",
                'type' => 'number',
                'default' => 1,
                'custom_attributes' => [
                    'min' => 1,
                    'step' => 1
                ]
            ], 'product_length_default' => [
                'title' => __('Длина товара', 'official_cdek'),
                'description' => "Длина товара по умолчанию в см",
                'type' => 'number',
                'desc_tip' => true,
                'default' => 10,
                'custom_attributes' => [
                    'min' => 1,
                    'step' => 1
                ]
            ], 'product_width_default' => [
                'title' => __('Ширина товара', 'official_cdek'),
                'description' => "Ширина товара по умолчанию в см",
                'type' => 'number',
                'desc_tip' => true,
                'default' => 10,
                'custom_attributes' => [
                    'min' => 1,
                    'step' => 1
                ]
            ], 'product_height_default' => [
                'title' => __('Высота товара', 'official_cdek'),
                'description' => "Высота товара по умолчанию в см",
                'type' => 'number',
                'desc_tip' => true,
                'default' => 10,
                'custom_attributes' => [
                    'min' => 1,
                    'step' => 1
                ]
            ], 'product_package_default_toggle' => [
                'title' => __('Габариты товара вкл/выкл', 'official_cdek'),
                'description' => 'Принудительно использовать габариты товара (длину, ширину и высоту) по умолчанию для всех товаров',
                'type' => 'checkbox',
                'desc_tip' => true,
                'default' => 'no'
            ], 'delivery_price_block_name' => [
                'title' => '<h3 style="text-align: center;">Cтоимость доставки</h3>',
                'type' => 'title',
                'class' => 'cdek_delivery_price_block_name',
            ], 'extra_cost' => [
                'title' => 'Доп. цена к доставке',
                'type' => 'number',
                'description' => "стоимость доставки в рублях которая будет добавлена к расчетной стоимости доставки",
                'desc_tip' => true,
                'default' => 0,
                'custom_attributes' => [
                    'min' => 1,
                    'step' => 1
                ]
            ], 'insurance' => [
                'title' => 'Страховка',
                'label' => 'Добавить расчет страховки к стоимости доставки',
                'type' => 'checkbox',
                'desc_tip' => true,
                'description' => "Расчитывается по сумме товаров в заказе",
            ], 'percentprice_title' => [
                'title' => 'Увеличение стоимости доставки в процентах',
                'type' => 'title',
            ], 'percentprice_toggle' => [
                'title' => '',
                'type' => 'checkbox',
                'label' => 'Добавить процентное увеличение к стоимости доставки',
            ], 'percentprice' => [
                'title' => '',
                'type' => 'number',
                'description' => "Введите процентное значение для добавления к стоимости доставки",
                'default' => 100,
                'custom_attributes' => [
                    'min' => 100,
                    'step' => 1
                ]
            ], 'fixprice_title' => [
                'title' => 'Фиксированная стоимость доставки',
                'type' => 'title',
            ], 'fixprice_toggle' => [
                'title' => '',
                'label' => 'Включить фиксированную стоимость доставки',
                'type' => 'checkbox',
            ], 'fixprice' => [
                'type' => 'number',
                'description' => "Стоимость в рублях",
                'default' => 0,
                'custom_attributes' => [
                    'min' => 0,
                    'step' => 1
                ]
            ], 'stepprice_title' => [
                'title' => 'Бесплатная доставка от суммы заказа',
                'type' => 'title',
            ], 'stepprice_toggle' => [
                'title' => '',
                'label' => 'Включить бесплатную доставку от суммы заказа',
                'type' => 'checkbox',
            ], 'stepprice' => [
                'type' => 'number',
                'description' => "Сумма заказа в рублях, от которой будет бесплатная доставка",
                'default' => 1000,
                'custom_attributes' => [
                    'min' => 0,
                    'step' => 1
                ]
            ], 'stepcodprice_title' => [
                'title' => 'Настройки наложенного платежа',
                'type' => 'title',
            ], 'stepcodprice' => [
                'title' => 'Бесплатная доставка от суммы заказа в рублях',
                'type' => 'number',
                'desc_tip' => true,
                'description' => "Не применяется если значение не задано",
                'default' => 1000,
                'custom_attributes' => [
                    'min' => 0,
                    'step' => 1
                ]
            ], 'percentcod' => [
                'title' => 'Наценка к заказу в процентах',
                'type' => 'number',
                'description' => "Расчитывается от стоимости заказа. 
                Меняет итоговую сумму в квитанции.
                <br> <b>Наценка отобразится только в квитанции.</b> Поэтому рекомендуется на странице чекаута проинформировать пользователя
                о наценки при отправки наложенным платежем.",
                'custom_attributes' => [
                    'min' => 100,
                    'step' => 1
                ]
            ], 'city_code_value' => [
                'type' => 'text',
                'css' => 'display: none;',
                'default' => __('44', 'official_cdek')
            ],
        ];
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