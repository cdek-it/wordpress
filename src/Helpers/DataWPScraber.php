<?php

namespace Cdek\Helpers;

class DataWPScraber
{
    public static function getData($data, $params): array {
        $result = [];
        foreach ($params as $param) {
            $result[$param] = $data->get_param($param);
        }
        return $result;
    }
}
