<?php

namespace Cdek;

use Cdek\Model\CourierMetaData;
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
        if ($this->cdekShippingSettings['has_packages_mode'] !== 'yes') {
            $validate = ValidateCreateOrderForm::validate($data);
            if (!$validate->state) {
                return $validate->response();
            }
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

        sleep(5);

        if (!$validate->state) {
            return $validate->response();
        }

        $cdekNumber = $this->getCdekOrderNumber($orderData->entity->uuid);
        $postOrderData['order_number'] = $cdekNumber;
        $postOrderData['order_uuid'] = $orderData->entity->uuid;
        OrderMetaData::updateMetaByOrderId($orderId, $postOrderData);

        return json_encode([
            'state' => true,
            'code' => $cdekNumber,
            'waybill' => '/wp-json/cdek/v1/get-waybill?number=' . $orderData->entity->uuid,
            'door' => Tariff::isTariffFromDoorByCode($postOrderData['tariff_id'])]);
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
        if ($selectedPaymentMethodId === 'cod') {
            $codPriceThreshold = (int)$this->cdekShippingSettings['stepcodprice'];
            $total = $this->getOrderPrice($order);
            if ($codPriceThreshold === 0 || $codPriceThreshold > $total) {
                $param['delivery_recipient_cost'] = [
                    'value' => $order->get_shipping_total()
                ];
            }
        }


//        if ($selectedPaymentMethodId === 'cod' && $codPriceThreshold > 0) {
//            $param['delivery_recipient_cost_adv'] = [
//                'sum' => $order->get_shipping_total(),
//                'threshold' => $codPriceThreshold
//            ];
//        }

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

    /**
     * 1. Получить order_uuid, если его нет - ничего не делать
     * 2. Запросить данные по заказу
     * 3. Проверить существует ли заказ
     * 4. если да ничего не делать
     * 5. если нет почистить кеш
     * Проверить откуда заказ, если от двери то удалить заявку, если нет пропустить
     * 6. Проверить есть ли заявка привязанная к заказу
     * 7. Если да, удалить заявку
     */
    public function deleteIfNotExist(int $order_id)
    {
        $meta = OrderMetaData::getMetaByOrderId($order_id);

        if ($meta['order_uuid'] === '') {
            return true;
        }

        $orderJson = $this->api->getOrder($meta['order_uuid']);
        $order = json_decode($orderJson);

        $validate = ValidateOrder::validate($order);
        if (!$validate->state()) {
            OrderMetaData::cleanMetaByOrderId($order_id);

            $courierCallMeta = CourierMetaData::getMetaByOrderId($order_id);
            if (array_key_exists('not_cons', $courierCallMeta) && $courierCallMeta['not_cons']) {
                $courierCall = new CallCourier();
                $courierCall->delete($order_id);
            }
        }

        return $validate->state();
    }

    /**
     * @param $order
     * @return string
     */
    protected function getOrderPrice($order): string
    {
        $total = number_format((float)$order->get_total() - $order->get_total_tax() - $order->get_total_shipping() - $order->get_shipping_tax(), wc_get_price_decimals(), '.', '');;
        return $total;
    }
}