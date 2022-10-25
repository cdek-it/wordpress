<?php

namespace Cdek\Model;

use Cdek\Data;

class AdminAuthSettingField implements Data
{

    const BLOCK_NAME = 'Авторизация';

    public function getFields(): array
    {
        return [
            'auth_block_name' => $this->getSellerBlockName(),
            'client_id' => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
        ];
    }

    private function getClientId(): array
    {
        return [
            'title' => 'Идентификатор клиента',
            'type' => 'text'
        ];
    }

    private function getClientSecret(): array
    {
        return [
            'title' => 'Секретный ключ клиента',
            'type' => 'text'
        ];
    }

    private function getSellerBlockName()
    {
        return [
            'title' => '<h3 style="border-bottom: 2px solid; text-align: center;">' . self::BLOCK_NAME . '</h3>',
            'type' => 'hidden',
            'class' => 'cdek_setting_block_name'
        ];
    }


}