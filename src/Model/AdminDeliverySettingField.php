<?php

namespace Cdek\Model;

use Cdek\Data;

class AdminDeliverySettingField implements Data
{

    const BLOCK_NAME = 'Доставка';

    public function getFields(): array
    {
        return [
            'delivery_block_name' => $this->getBlockName(),
            'tariff_list' => $this->getTariffList(),
            'service_list' => $this->getServiceList(),
            'product_weight_default' => $this->getProductWeightDefault(),
            'map_layer' => $this->getMapLayer(),
            'yandex_map_api_key' => $this->getYandexMapApiKey(),
            'has_packages_mode' => $this->getManyPackagesMode(),
            'city' => $this->getCity(),
            'address' => $this->getAddress(),
            'map' => $this->getMap(),
            'pvz_address' => $this->getPvzAddress(),
            'pvz_code' => $this->getPvzCode(),
            'city_code_value' => $this->getCityCodeValue()
        ];
    }

    private function getTariffList(): array
    {
        return [
            'title' => 'Тарифы',
            'type' => 'multiselect',
            'options' => Tariff::getTariffList(),
            'description' => "Для выбора нескольких тарифов удерживайте клавишу \"CTRL\" и левой кнопкой 
                            мыши выберите тарифы.<br> Если отправка производится со склада, то рекомендуется 
                            выбирать тарифы только от склада. <br> Иначе у пользователя будет
                            выбор тарифов \"от двери\"",
        ];
    }

    private function getServiceList(): array
    {
        return [
            'title' => 'Услуги',
            'type' => 'multiselect',
            'options' => Service::getServiceList()
        ];
    }

    private function getProductWeightDefault(): array
    {
        return [
            'title' => 'Вес одной единицы товара по умолчанию в кг',
            'type' => 'text',
            'default' => 1,
            'description' => "У всех товаров должен быть указан вес, если есть товары без указанного <br> 
                                    веса то для таких товаров будет подставляться значение из этого поля. <br>
                                    Это повлияет на точность расчета доставки. Значение по умолчанию 1 кг.",
        ];
    }

    private function getMapLayer(): array
    {
        return [
            'title' => 'Слой карты',
            'type' => 'select',
            'options' => ['OpenStreetMap', 'YandexMap']
        ];
    }

    private function getYandexMapApiKey(): array
    {
        return [
            'type' => 'hidden',
            'placeholder' => 'Api Key'
        ];
    }

    private function getManyPackagesMode(): array
    {
        return [
            'title' => 'Многоместка',
            'type' => 'checkbox',
            'default' => 'no',
            'description' => "При включенном режиме 'Многоместка', на детальной странице заказа появится
                 возможность создать несколько упаковок для одного заказа и распределить товары по созданным упаковкам"
        ];
    }

    private function getCity(): array
    {
        return [
            'title' => 'Город отправления',
            'type' => 'text',
            'default' => 'Москва'
        ];
    }

    private function getAddress(): array
    {
        return [
            'title' => 'Адрес',
            'type' => 'text',
            'description' => "Адрес отправления для тарифов \"от двери\""
        ];
    }

    private function getMap(): array
    {
        return [
            'type' => 'hidden',
            'title' => 'Выбрать ПВЗ на карте',
        ];
    }

    private function getPvzAddress(): array
    {
        return [
            'type' => 'text',
            'class' => 'readonly',
            'description' => "Адрес отправления для тарифов \"от склада\""
        ];
    }

    private function getPvzCode(): array
    {
        return [
            'type' => 'hidden',
        ];
    }

    private function getCityCodeValue(): array
    {
        return [
            'type' => 'text',
            'css' => 'display: none;',
            'default' => 44
        ];
    }

    private function getBlockName(): array
    {
        return [
            'title' => '<h3 style="border-bottom: 2px solid; text-align: center;">' . self::BLOCK_NAME . '</h3>',
            'type' => 'hidden',
            'class' => 'cdek_setting_block_name'
        ];
    }
}