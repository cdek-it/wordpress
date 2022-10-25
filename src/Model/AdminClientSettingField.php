<?php

namespace Cdek\Model;

use Cdek\Data;

class AdminClientSettingField implements Data
{

    const BLOCK_NAME = 'Клиент';

    public function getFields(): array
    {
        return [
            'seller_block_name' => $this->getSellerBlockName(),
            'seller_name' => $this->getSellerName(),
            'seller_phone' => $this->getSellerPhone(),
            'seller_address' => $this->getSellerAddress(),
            'shipper_name' => $this->getShipperName(),
            'shipper_address' => $this->getShipperAddress(),
        ];
    }

    private function getSellerName(): array
    {
        return [
            'title' => 'ФИО',
            'type' => 'text',
        ];
    }

    private function getSellerPhone(): array
    {
        return [
            'title' => 'Телефон',
            'type' => 'text',
            'description' => 'Должен передаваться в международном формате: код страны 
            (для России +7) и сам номер (10 и более цифр)'
        ];
    }

    private function getSellerAddress(): array
    {
        return [
            'title' => 'Адрес истинного продавца',
            'type' => 'text',
            'description' => 'Адрес истинного продавца. Используется при печати инвойсов для отображения адреса настоящего 
                продавца товара, либо торгового названия. Для международных заказов'
        ];
    }

    private function getShipperName(): array
    {
        return [
            'title' => 'Грузоотправитель',
            'type' => 'text',
            'description' => 'Название компании грузоотправителя для международных заказов'
        ];
    }

    private function getShipperAddress(): array
    {
        return [
            'title' => __('Адрес грузоотправителя', 'official_cdek'),
            'type' => 'text',
            'description' => 'Адрес компании грузоотправителя для международных заказов'
        ];
    }

    private function getSellerBlockName(): array
    {
        return [
            'title' => '<h3 style="border-bottom: 2px solid; text-align: center;">' . self::BLOCK_NAME . '</h3>',
            'type' => 'hidden',
            'class' => 'cdek_setting_block_name'
        ];
    }


}