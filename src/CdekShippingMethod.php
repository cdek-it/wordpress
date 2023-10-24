<?php

namespace Cdek;

use Cdek\Enums\BarcodeFormat;
use Cdek\Helpers\DeliveryCalc;
use Cdek\Model\Tariff;
use WC_Settings_API;
use WC_Shipping_Method;

class CdekShippingMethod extends WC_Shipping_Method {
    public function __construct($instance_id = 0) {
        parent::__construct($instance_id);
        $this->id                 = Config::DELIVERY_NAME;
        $this->instance_id        = absint($instance_id);
        $this->method_title       = 'Cdek Shipping';
        $this->method_description = 'Custom Shipping Method for Cdek';
        $this->supports           = [
            'settings',
            'shipping-zones',
            'instance-settings',
        ];
        $this->enabled            = 'yes';
        $this->init();
    }

    public function init(): void {
        $this->title = 'CDEK Shipping';
        $this->init_settings();
        add_action('woocommerce_update_options_shipping_'.$this->id, [$this, 'process_admin_options']);
        $this->init_form_fields();
    }

    public function init_form_fields(): void {
        $this->instance_form_fields = [
            'extra_cost'           => [
                'title'             => 'Доп. цена к доставке',
                'type'              => 'number',
                'description'       => "стоимость доставки в рублях которая будет добавлена к расчетной стоимости доставки",
                'desc_tip'          => true,
                'default'           => '',
                'custom_attributes' => [
                    'min'  => 0,
                    'step' => 1,
                ],
            ],
        ];

        $this->form_fields = [
            'auth_block_name'                => [
                'title' => '<h3 style="text-align: center;">Авторизация</h3>',
                'type'  => 'title',
                'class' => 'cdek_setting_block_name',
            ],
            'test_mode'                      => [
                'title'       => 'Тестовый режим',
                'type'        => 'checkbox',
                'default'     => false,
                'desc_tip'    => true,
                'description' => 'При включенном режиме используется тестовое апи',
            ],
            'client_id'                      => [
                'title'             => 'Идентификатор',
                'type'              => 'text',
                'custom_attributes' => [
                    'required' => true,
                ],
            ],
            'client_secret'                  => [
                'title'             => 'Секретный ключ',
                'type'              => 'text',
                'custom_attributes' => [
                    'required' => true,
                ],
            ],
            'yandex_map_api_key'             => [
                'type'              => 'text',
                'title'             => 'Ключ Яндекс.Карты',
                'description'       => 'Ключ доступа к API Яндекс. Процесс генерации описан на <a rel="noopener nofollower" href="https://yandex.ru/dev/jsapi-v2-1/doc/ru/#get-api-key" target="_blank">странице</a>.',
                'custom_attributes' => [
                    'required' => true,
                ],
            ],
            'seller_block_name'              => [
                'title' => '<h3 style="text-align: center;">Клиент</h3>',
                'type'  => 'title',
                'class' => 'cdek_setting_block_name',
            ],
            'seller_name'                    => [
                'title'             => 'ФИО',
                'type'              => 'text',
                'custom_attributes' => [
                    'required' => true,
                ],
            ],
            'seller_phone'                   => [
                'title'             => 'Телефон',
                'type'              => 'text',
                'desc_tip'          => true,
                'description'       => 'Должен передаваться в международном формате: код страны (для России +7) и сам номер (10 и более цифр)',
                'custom_attributes' => [
                    'required' => true,
                ],
            ],
            'international_title'            => [
                'title' => 'Международные заказы',
                'type'  => 'title',
            ],
            'international_mode'             => [
                'title'       => 'Включить режим международных заказов',
                'type'        => 'checkbox',
                'desc_tip'    => true,
                'description' => "При включенном режиме международных заказов, на странице чекаута появятся дополнительные поля:
                серия паспорта, номер паспорта, дата выдачи, отдел, ИНН, дата рождения.",
                'default'     => 'no',
            ],
            'seller_address'                 => [
                'title'       => 'Адрес истинного продавца',
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => 'Адрес истинного продавца. Используется при печати инвойсов для отображения адреса настоящего
                продавца товара, либо торгового названия. Для международных заказов',
            ],
            'shipper_name'                   => [
                'title'       => 'Грузоотправитель',
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => 'Название компании грузоотправителя для международных заказов',
            ],
            'shipper_address'                => [
                'title'       => 'Адрес грузоотправителя',
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => 'Адрес компании грузоотправителя для международных заказов',
            ],
            'passport_series'                => [
                'title' => 'Серия паспорта',
                'type'  => 'text',
            ],
            'passport_number'                => [
                'title'             => 'Номер паспорта',
                'type'              => 'text',
                'custom_attributes' => [
                    'pattern' => '\d*',
                ],
            ],
            'passport_date_of_issue'         => [
                'title'       => 'Дата выдачи паспорта',
                'type'        => 'date',
                'date_format' => 'd.m.Y',
            ],
            'passport_organization'          => [
                'title' => 'Орган выдачи паспорта',
                'type'  => 'text',
            ],
            'tin'                            => [
                'title'             => 'ИНН',
                'type'              => 'text',
                'desc_tip'          => true,
                'custom_attributes' => [
                    'pattern' => '\d*',
                ],
            ],
            'passport_date_of_birth'         => [
                'title'       => 'Дата рождения',
                'type'        => 'date',
                'date_format' => 'd.m.Y',
            ],
            'delivery_block_name'            => [
                'title' => '<h3 style="text-align: center;">Доставка</h3>',
                'type'  => 'title',
                'class' => 'cdek_delivery_block_name',
            ],
            'tariff_list'                    => [
                'title'       => 'Тарифы',
                'type'        => 'multiselect',
                'desc_tip'    => true,
                'options'     => Tariff::getTariffList(),
                'description' => "Для выбора нескольких тарифов удерживайте клавишу \"CTRL\" и левой кнопкой мыши выберите тарифы.",
                'css'         => 'height: 400px;',
            ],
            'tariff_name'                    => [
                'title'       => 'Изменить название тарифа',
                'type'        => 'text',
                'description' => "В списке тарифов в поле \"Тарифы\" в скобках указан код тарифа. Для изменения названия тарифа
                в поле добавляется запись в формате код-название, для множественного изменения, тарифы отделяются точкой с запятой, например
                запись которая изменит название 136 и 137 тарифа выглядит так: <b>136-Доставка до пвз;137-Доставка курьером</b> <br>
                Если значение не задано то названия тарифов будут стандартными.",
            ],
            'has_packages_mode'              => [
                'title'       => 'Многоместка',
                'type'        => 'checkbox',
                'desc_tip'    => true,
                'description' => "При включенном режиме 'Многоместка', на детальной странице заказа появится
                 возможность создать несколько упаковок для одного заказа и распределить товары по созданным упаковкам",
                'default'     => 'no',
            ],
            'extra_day'                      => [
                'title'             => 'Доп. дни к доставке',
                'type'              => 'number',
                'desc_tip'          => true,
                'description'       => "Колличество дней будет добавлено к расчетному времени доставки",
                'default'           => 0,
                'custom_attributes' => [
                    'min'  => 0,
                    'step' => 1,
                ],
            ],
            'map'                            => [
                'type'  => 'hidden',
                'title' => 'Выбрать адреса для отправки на карте',
            ],
            'pvz_code'                       => [
                'type' => 'hidden',
            ],
            'address'                        => [
                'type' => 'hidden',
            ],
            'token'                         => [
                'type' => 'hidden',
            ],
            'token_exp'                     => [
                'type' => 'hidden',
            ],
            'package_setting_block_name'     => [
                'title' => '<h3 style="text-align: center;">Габариты</h3>',
                'type'  => 'title',
                'class' => 'cdek_package_setting_block_name',
            ],
            'product_weight_default'         => [
                'title'             => 'Вес одной единицы товара по умолчанию в (' . get_option('woocommerce_weight_unit').')',
                'desc_tip'          => true,
                'description'       => "У всех товаров должен быть указан вес,
                            если есть товары без указанного <br> веса то для таких товаров будет подставляться значение из этого поля. <br>
                            Это повлияет на точность расчета доставки. Значение по умолчанию 1 единица измерения веса заданная в настройках.",
                'type'              => 'number',
                'default'           => 1,
                'custom_attributes' => [
                    'min'  => 0,
                    'step' => 0.01
                ],
            ],
            'product_length_default'         => [
                'title'             => 'Длина товара',
                'description'       => "Длина товара по умолчанию в см",
                'type'              => 'number',
                'desc_tip'          => true,
                'default'           => 10,
                'custom_attributes' => [
                    'min'  => 1,
                    'step' => 1,
                ],
            ],
            'product_width_default'          => [
                'title'             => 'Ширина товара',
                'description'       => "Ширина товара по умолчанию в см",
                'type'              => 'number',
                'desc_tip'          => true,
                'default'           => 10,
                'custom_attributes' => [
                    'min'  => 1,
                    'step' => 1,
                ],
            ],
            'product_height_default'         => [
                'title'             => 'Высота товара',
                'description'       => "Высота товара по умолчанию в см",
                'type'              => 'number',
                'desc_tip'          => true,
                'default'           => 10,
                'custom_attributes' => [
                    'min'  => 1,
                    'step' => 1,
                ],
            ],
            'product_package_default_toggle' => [
                'title'       => 'Габариты товара вкл/выкл',
                'description' => 'Принудительно использовать габариты товара (длину, ширину и высоту) по умолчанию для всех товаров',
                'type'        => 'checkbox',
                'desc_tip'    => true,
                'default'     => 'no',
            ],
            'delivery_price_block_name'      => [
                'title' => '<h3 style="text-align: center;">Cтоимость доставки</h3>',
                'type'  => 'title',
                'class' => 'cdek_delivery_price_block_name',
            ],
            'extra_cost'                     => [
                'title'             => 'Доп. цена к доставке',
                'type'              => 'number',
                'description'       => "стоимость доставки в рублях которая будет добавлена к расчетной стоимости доставки",
                'desc_tip'          => true,
                'default'           => 0,
                'custom_attributes' => [
                    'min'  => 1,
                    'step' => 1,
                ],
            ],
            'insurance'                      => [
                'title'       => 'Страховка',
                'label'       => 'Добавить расчет страховки к стоимости доставки',
                'type'        => 'checkbox',
                'desc_tip'    => true,
                'description' => "Расчитывается по сумме товаров в заказе",
            ],
            'percentprice_title'             => [
                'title' => 'Увеличение стоимости доставки в процентах',
                'type'  => 'title',
            ],
            'percentprice_toggle'            => [
                'title' => '',
                'type'  => 'checkbox',
                'label' => 'Добавить процентное увеличение к стоимости доставки',
            ],
            'percentprice'                   => [
                'title'             => '',
                'type'              => 'number',
                'description'       => "Введите процентное значение для добавления к стоимости доставки",
                'default'           => 100,
                'custom_attributes' => [
                    'min'  => 100,
                    'step' => 1,
                ],
            ],
            'fixprice_title'                 => [
                'title' => 'Фиксированная стоимость доставки',
                'type'  => 'title',
            ],
            'fixprice_toggle'                => [
                'title' => '',
                'label' => 'Включить фиксированную стоимость доставки',
                'type'  => 'checkbox',
            ],
            'fixprice'                       => [
                'type'              => 'number',
                'description'       => "Стоимость в рублях",
                'default'           => 0,
                'custom_attributes' => [
                    'min'  => 0,
                    'step' => 1,
                ],
            ],
            'stepprice_title'                => [
                'title' => 'Бесплатная доставка от суммы заказа',
                'type'  => 'title',
            ],
            'stepprice_toggle'               => [
                'title' => '',
                'label' => 'Включить бесплатную доставку от суммы заказа',
                'type'  => 'checkbox',
            ],
            'stepprice'                      => [
                'type'              => 'number',
                'description'       => "Сумма заказа в рублях, от которой будет бесплатная доставка",
                'default'           => 1000,
                'custom_attributes' => [
                    'min'  => 0,
                    'step' => 1,
                ],
            ],
            'stepcodprice_title'             => [
                'title'       => 'Настройки наложенного платежа',
                'type'        => 'title',
                'description' => 'Настройки для наложенного платежа применяются только во время отправки заказа из админ
                панели и для пользователя на странице чекаута не отображаются',
            ],
            'stepcodprice'                   => [
                'title'             => 'Бесплатная доставка от суммы заказа в рублях',
                'type'              => 'number',
                'desc_tip'          => true,
                'description'       => "Не применяется если значение не задано",
                'default'           => 1000,
                'custom_attributes' => [
                    'min'  => 0,
                    'step' => 1,
                ],
            ],
            'percentcod'                     => [
                'title'             => 'Наценка к заказу в процентах',
                'type'              => 'number',
                'description'       => "Расчитывается от стоимости заказа.
                Меняет итоговую сумму в квитанции.
                <br> <b>Наценка отобразится только в квитанции.</b> Поэтому рекомендуется на странице чекаута проинформировать пользователя
                о наценки при отправки наложенным платежем.",
                'custom_attributes' => [
                    'min'  => 100,
                    'step' => 1,
                ],
            ],
            'city_code_value'                => [
                'type'    => 'text',
                'css'     => 'display: none;',
                'default' => '44',
            ],
            'barcode_format_title'           => [
                'title' => 'Настройки печати',
                'type'  => 'title',
            ],
            'barcode_format'                 => [
                'title'   => 'Формат ШК',
                'type'    => 'select',
                'options' => BarcodeFormat::getAll(),
            ],
        ];
    }

    public function get_option($key, $empty_value = null) {
        // Instance options take priority over global options.
        if ($this->instance_id && array_key_exists($key, $this->get_instance_form_fields())) {
            $instanceValue = $this->get_instance_option($key, $empty_value);

            if (!empty($instanceValue)) {
                return $instanceValue;
            }
        }

        // Return global option.
        $option = apply_filters('woocommerce_shipping_'.$this->id.'_option', WC_Settings_API::get_option($key, $empty_value),
            $key, $this);

        return $option;
    }

    public function calculate_shipping($package = []): void {
        $deliveryCalc = new DeliveryCalc($this->get_instance_id());
        if (!$deliveryCalc->calculate($package)) {
            return;
        }

        foreach ($deliveryCalc->getRates() as $rate) {
            $this->add_rate($rate);
        }
    }
}
