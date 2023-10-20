<?php

namespace Cdek\Actions;

use Cdek\CdekApi;
use Cdek\Helper;
use Cdek\Helpers\CheckoutHelper;
use Cdek\Model\OrderMetaData;
use Cdek\Model\Tariff;
use Cdek\Validator\ValidateCreateOrderForm;

class CreateOrder {
    protected CdekApi $api;

    public function __construct() {
        $this->api = new CdekApi;
    }

    /**
     * @throws \JsonException
     */
    public function __invoke($data): array {
        if (Helper::getActualShippingMethod()->get_option('has_packages_mode') !== 'yes') {
            $validate = ValidateCreateOrderForm::validate($data);
            if (!$validate->state) {
                return $validate->response();
            }
        }

        $orderId                      = $data->get_param('package_order_id');
        $order                        = wc_get_order($orderId);
        $postOrderData                = OrderMetaData::getMetaByOrderId($orderId);
        $postOrderData['tariff_code'] = CheckoutHelper::getOrderShippingMethod($order)->get_meta('tariff_code') ?:
            $postOrderData['tariff_id'];
        $postOrderData['type']        = Tariff::getTariffType($postOrderData['tariff_code']);
        $param                        = setPackage($data, $orderId, $postOrderData['currency'],
            $postOrderData['type']); //data передается в сыром виде

        $param = $this->createRequestData($postOrderData, $order, $param);

        $orderData = json_decode($this->api->createOrder($param), true, 512, JSON_THROW_ON_ERROR);

        sleep(5);

        $cdekNumber                    = $this->getCdekOrderNumber($orderData->entity->uuid);
        $postOrderData['order_number'] = $cdekNumber;
        $postOrderData['order_uuid']   = $orderData->entity->uuid;
        OrderMetaData::updateMetaByOrderId($orderId, $postOrderData);

        return [
            'state' => true,
            'code'  => $cdekNumber,
            'door'  => Tariff::isTariffFromDoor($postOrderData['tariff_code']),
        ];
    }

    private function createRequestData($postOrderData, $order, $param): array {
        if (Tariff::isTariffToOffice($postOrderData['tariff_code'])) {
            $param['delivery_point'] = $postOrderData['pvz_code'];
        } else {
            $param['to_location'] = [
                'code'    => $postOrderData['city_code'],
                'address' => $order->get_shipping_address_1(),
            ];
        }

        $param['type'] = $postOrderData['type'];

        $param['recipient'] = [
            'name'   => $order->get_billing_first_name().' '.$order->get_billing_last_name(),
            'email'  => $order->get_billing_email(),
            'phones' => [
                'number' => $order->get_billing_phone(),
            ],
        ];

        if (Helper::getActualShippingMethod()->get_option('international_mode') === 'yes') {
            $param['recipient']['passport_series']        = $order->get_meta('_passport_series', true);
            $param['recipient']['passport_number']        = $order->get_meta('_passport_number', true);
            $param['recipient']['passport_date_of_issue'] = $order->get_meta('_passport_date_of_issue', true);
            $param['recipient']['passport_organization']  = $order->get_meta('_passport_organization', true);
            $param['recipient']['tin']                    = $order->get_meta('_tin', true);
            $param['recipient']['passport_date_of_birth'] = $order->get_meta('_passport_date_of_birth', true);
        }

        $param['tariff_code'] = $postOrderData['tariff_code'];

        $selectedPaymentMethodId = $order->get_payment_method();
        if ($selectedPaymentMethodId === 'cod') {
            $codPriceThreshold = (int) Helper::getActualShippingMethod()->get_option('stepcodprice');
            $total             = $this->getOrderPrice($order);
            if ($codPriceThreshold === 0 || $codPriceThreshold > $total) {
                $param['delivery_recipient_cost'] = [
                    'value' => $order->get_shipping_total(),
                ];
            }
        }

        return $param;
    }

    /**
     * @param $order
     *
     * @return string
     */
    protected function getOrderPrice($order): string {
        $total = number_format((float) $order->get_total() -
                               $order->get_total_tax() -
                               $order->get_total_shipping() -
                               $order->get_shipping_tax(), wc_get_price_decimals(), '.', '');

        return $total;
    }

    private function getCdekOrderNumber($orderUuid, $iteration = 1) {
        if ($iteration === 5) {
            return $orderUuid;
        }
        $orderInfoJson = $this->api->getOrder($orderUuid);
        $orderInfo     = json_decode($orderInfoJson, true);

        return $orderInfo['entity']['cdek_number'] ?? $this->getCdekOrderNumber($orderUuid, $iteration + 1);
    }
}
