<?php

namespace Cdek;

use Cdek\Model\Tariff;

class DeliveryCalc
{
    public $rates = [];

    public function calculate($package, $id): bool
    {
        $api = new CdekApi();
        if (!$api->checkAuth()) {
            return false;
        }

        $deliveryParam['city'] = $package["destination"]['city'];
        if (!$deliveryParam['city']) {
            return false;
        }

        $cdekShippingSettings = Helper::getSettingDataPlugin();
        $tariffList = $cdekShippingSettings['tariff_list'];
        $deliveryParam['state'] = $this->getState($package["destination"]);
        $deliveryParam['package_data'] = $this->getPackagesData($package['contents']);
        $services = $cdekShippingSettings['service_list'];

        $api = new CdekApi();
        foreach ($tariffList as $tariff) {
            $deliveryParam['selected_services'] = $this->getServicesList($services, $tariff);

            $delivery = json_decode($api->calculate($deliveryParam, $tariff));

            if (!$this->checkDeliveryResponse($delivery)) {
                continue;
            }

            $this->rates[] = [
                'id' => $id . '_' . $tariff,
                'label' => sprintf(
                    "CDEK: %s, (%s-%s дней)",
                    Tariff::getTariffNameByCode($tariff),
                    $delivery->period_min,
                    $delivery->period_max
                ),
                'cost' => $delivery->total_sum,
                'meta_data' => ['tariff_code' => $tariff]
            ];
        }

        return true;
    }

    protected function getState($destination): string
    {
        $state = '';
        if (array_key_exists('state', $destination)) {
            $state = $destination['state'];
        }
        return $state;
    }

    protected function getPackagesData($contents): array
    {
        $totalWeight = 0;
        $lengthList = [];
        $widthList = [];
        $heightList = [];
        foreach ($contents as $productGroup) {
            $quantity = $productGroup['quantity'];
            $weight = $productGroup['data']->get_weight();
            $dimension = get_option('woocommerce_dimension_unit');
            if ($dimension === 'mm') {
                $lengthList[] = (int)((int)$productGroup['data']->get_length() / 10);
                $widthList[] = (int)((int)$productGroup['data']->get_width() / 10);
                $heightList[] = (int)((int)$productGroup['data']->get_height() / 10);
            } else {
                $lengthList[] = (int)$productGroup['data']->get_length();
                $widthList[] = (int)$productGroup['data']->get_width();
                $heightList[] = (int)$productGroup['data']->get_height();
            }

            $weightClass = new WeightCalc();
            $weight = $weightClass->getWeight($weight);
            $totalWeight += $quantity * $weight;
        }

        rsort($lengthList);
        rsort($widthList);
        rsort($heightList);

        $length = $lengthList[0];
        $width = $widthList[0];
        $height = $heightList[0];

        return ['length' => $length, 'width' => $width, 'height' => $height, 'weight' => $totalWeight];
    }

    protected function getServicesList($services, $tariff): array
    {
        $servicesListForParam = [];
        if ($services !== "") {
            foreach ($services as $service) {
                if ($service === 'DELIV_RECEIVER' && $tariff == '62') {
                    $servicesListForParam['code'] = $service;
                }
            }
        }
        return $servicesListForParam;
    }

    protected function checkDeliveryResponse($delivery): bool
    {
        if (!property_exists($delivery, 'status')) {
            return true;
        } else {
            return false;
        }
    }
}