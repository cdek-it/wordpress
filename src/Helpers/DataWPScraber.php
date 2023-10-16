<?php

namespace Cdek\Helpers;

use Cdek\Config;

class DataWPScraber {
    public static function getData($data, $params): array {
        $result = [];
        foreach ($params as $param) {
            $result[$param] = $data->get_param($param);
        }

        return $result;
    }

    public static function hideMeta(array $hiddenMeta): array {
        $hiddenMeta[] = Config::ADDRESS_HASH_META_KEY;
        return $hiddenMeta;
    }
}
