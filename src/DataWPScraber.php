<?php

namespace Cdek;

class DataWPScraber
{
    public static function getData($data, $params)
    {
        $result = [];
        foreach ($params as $param) {
            $result[$param] = $data->get_param($param);
        }
        return $result;
    }
}