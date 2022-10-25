<?php

namespace Cdek\Model;

use Cdek\CdekApi;

class FieldObjArray
{
    public static function get($setting): array
    {
        $api = new CdekApi($setting);

        if ($api->checkAuth()) {
            return [
                new AdminAuthSettingField(),
                new AdminClientSettingField(),
                new AdminDeliverySettingField(),
            ];
        } else {
            return [
                new AdminAuthSettingField(),
            ];
        }
    }
}