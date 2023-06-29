<?php

namespace Cdek;

use Cdek\Model\OrderMetaData;
use Cdek\Model\Tariff;
use Cdek\Validator\ValidateCityCode;
use Cdek\Validator\ValidateCreateOrderForm;
use Cdek\Validator\ValidateOrder;

class CreateOrder
{
    protected $api;
    protected $cdekShippingSettings;

    public function __construct()
    {
        $this->api = new CdekApi();
        $this->cdekShippingSettings = Helper::getSettingDataPlugin();
    }

    public function createOrder($data)
    {
        $validate = ValidateCreateOrderForm::validate($data);
        if (!$validate->state) {
            return $validate->response();
        }

        $orderId = $data->get_param('package_order_id');
        $postOrderData = OrderMetaData::getMetaByOrderId($orderId);
        $param = setPackage($data, $orderId, $postOrderData['currency']); //data передается в сыром виде
        $order = wc_get_order($orderId);
        $cityCode = getCityCode($postOrderData['city_code'], $order);

        $validate = ValidateCityCode::validate($cityCode);
        if (!$validate->state) {
            return $validate->response();
        }

        $orderData = $this->create($postOrderData, $order, $param);

        $validate = ValidateOrder::validate($orderData);
        if (!$validate->state) {
            return $validate->response();
        }

        $cdekNumber = $this->getCdekOrderNumber($orderData->entity->uuid);

        //cdek_order_uuid и cdek_order_waybill старый формат для поддержки старых заказов, со временем удалить
        $postOrderData['cdek_order_uuid'] = $cdekNumber;
        $postOrderData['cdek_order_waybill'] = $orderData->entity->uuid;
        $postOrderData['order_number'] = $cdekNumber;
        $postOrderData['order_uuid'] = $orderData->entity->uuid;
//    $postOrderData['created'] = true; Заменить флаг создания заказа
        OrderMetaData::updateMetaByOrderId($orderId, $postOrderData);
        return json_encode(['state' => true, 'code' => $cdekNumber, 'waybill' => '/wp-json/cdek/v1/get-waybill?number=' . $orderData->entity->uuid]);
    }

    public function create($postOrderData, $order, $param)
    {
        $param = $this->createRequestData($postOrderData, $order, $param);
        $orderDataJson = $this->api->createOrder($param);
        return json_decode($orderDataJson);
    }

    public function createRequestData($postOrderData, $order, $param)
    {
        if ((int)Tariff::getTariffTypeToByCode($postOrderData['tariff_id'])) {
            $param['delivery_point'] = $postOrderData['pvz_code'];
        } else {
            $param['to_location'] = [
                'code' => $postOrderData['city_code'],
                'address' => $order->get_shipping_address_1()
            ];
        }

        $param['recipient'] = [
            'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'email' => $order->get_billing_email(),
            'phones' => [
                'number' => $order->get_billing_phone()
            ]
        ];

        if ($this->cdekShippingSettings['international_mode'] === 'yes') {
            $param['recipient']['passport_series'] = $order->get_meta('_passport_series', true);
            $param['recipient']['passport_number'] = $order->get_meta('_passport_number', true);
            $param['recipient']['passport_date_of_issue'] = $order->get_meta('_passport_date_of_issue', true);
            $param['recipient']['passport_organization'] = $order->get_meta('_passport_organization', true);
            $param['recipient']['tin'] = $order->get_meta('_tin', true);
            $param['recipient']['passport_date_of_birth'] = $order->get_meta('_passport_date_of_birth', true);
        }

        $param['tariff_code'] = $postOrderData['tariff_id'];
        $param['print'] = 'waybill';

        $selectedPaymentMethodId = $order->get_payment_method();
        $codPriceThreshold = (int)$this->cdekShippingSettings['stepcodprice'];

        //threshold
        if ($selectedPaymentMethodId === 'cod' && $codPriceThreshold > 0) {
            $param['delivery_recipient_cost_adv'] = [
                'sum' => $order->get_shipping_total(),
                'threshold' => $codPriceThreshold
            ];
        }

        return $param;
    }

    public function getCdekOrderNumber($orderUuid, $iteration = 1)
    {
        if ($iteration === 5) {
            return $orderUuid;
        }
        $orderInfoJson = $this->api->getOrder($orderUuid);
        $orderInfo = json_decode($orderInfoJson);
        if (!property_exists($orderInfo->entity, 'cdek_number')) {
            //если номер заказа не успел сформироваться запрашиваем еще раз
            $this->getCdekOrderNumber($orderUuid, $iteration + 1);
        }
        return $orderInfo->entity->cdek_number;
    }
}