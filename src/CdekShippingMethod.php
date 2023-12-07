<?php

namespace Cdek;

use Cdek\Enums\BarcodeFormat;
use Cdek\Helpers\DeliveryCalc;
use Cdek\Model\Tariff;
use WC_Settings_API;
use WC_Shipping_Method;

class CdekShippingMethod extends WC_Shipping_Method
{
    public function __construct($instance_id = 0)
    {
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

    final public function init(): void
    {
        $this->title = 'CDEK Shipping';
        $this->init_settings();
        add_action('woocommerce_update_options_shipping_'.$this->id, [$this, 'process_admin_options']);
        $this->init_form_fields();
    }

    final public function init_form_fields(): void
    {
        $this->instance_form_fields = [
            'use_delivery_price_rules' => [
                'title' => 'Использовать правила отображения суммы доставки для зоны',
                'type'  => 'checkbox',
            ],
            'delivery_price_rules'     => [
                'title' => 'Правила отображения суммы доставки',
                'label' => '',
                'type'  => 'hidden',
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
            'seller_company'                 => [
                'title'             => 'Название компании',
                'type'              => 'text',
                'custom_attributes' => [
                    'required' => true,
                ],
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
            'seller_email'                   => [
                'title'             => 'Электронный адрес почты компании',
                'type'              => 'text',
                'custom_attributes' => [
                    'required' => true,
                    'type'     => 'email',
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
            'automate_orders'                => [
                'title'       => 'Автоматически создавать заказы в СДЭК',
                'type'        => 'checkbox',
                'description' => 'При наличии информации о габаритах и корректно заполненных адреса отправки накладная СДЭК будет создана автоматически',
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
            'token'                          => [
                'type' => 'hidden',
            ],
            'package_setting_block_name'     => [
                'title' => '<h3 style="text-align: center;">Габариты</h3>',
                'type'  => 'title',
                'class' => 'cdek_package_setting_block_name',
            ],
            'product_weight_default'         => [
                'title'             => 'Вес одной единицы товара по умолчанию в ('.
                                       get_option('woocommerce_weight_unit').
                                       ')',
                'desc_tip'          => true,
                'description'       => "У всех товаров должен быть указан вес,
                            если есть товары без указанного <br> веса то для таких товаров будет подставляться значение из этого поля. <br>
                            Это повлияет на точность расчета доставки. Значение по умолчанию 1 единица измерения веса заданная в настройках.",
                'type'              => 'number',
                'default'           => 1,
                'custom_attributes' => [
                    'min'  => 0,
                    'step' => 0.01,
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
            'insurance'                      => [
                'title'       => 'Страховка',
                'label'       => 'Добавить расчет страховки к стоимости доставки',
                'type'        => 'checkbox',
                'desc_tip'    => true,
                'description' => "Расчитывается по сумме товаров в заказе",
            ],
            'delivery_price_rules'           => [
                'title' => 'Правила отображения суммы доставки',
                'label' => '',
                'type'  => 'hidden',
            ],
            'stepcodprice_title'             => [
                'title'       => 'Настройки наложенного платежа',
                'type'        => 'title',
                'description' => 'Настройки для наложенного платежа применяются только во время отправки заказа из админ
                панели и для пользователя на странице чекаута не отображаются',
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

    public function get_option($key, $empty_value = null)
    {
        // Instance options take priority over global options.
        if ($this->instance_id && array_key_exists($key, $this->get_instance_form_fields())) {
            $instanceValue = $this->get_instance_option($key, $empty_value);

            if (array_key_exists("use_$key", $this->get_instance_form_fields())) {
                if ($this->get_instance_option("use_$key", false)) {
                    return $instanceValue;
                }
            } elseif (!empty($instanceValue)) {
                return $instanceValue;
            }
        }

        // Return global option.
        $option = apply_filters('woocommerce_shipping_'.$this->id.'_option',
                                WC_Settings_API::get_option($key, $empty_value), $key, $this);

        return $option;
    }

    public function calculate_shipping($package = []): void
    {
        $deliveryCalc = new DeliveryCalc($this->get_instance_id());
        if (!$deliveryCalc->calculate($package)) {
            return;
        }

        foreach ($deliveryCalc->getRates() as $rate) {
            $this->add_rate($rate);
        }
    }
}
