<?php

namespace Cdek;

use Cdek\Model\OrderMetaData;
use Cdek\Model\Validate;
use Cdek\Validator\ValidateDeleteOrder;
use Cdek\Validator\ValidateGetOrder;

class DeleteOrder
{

    protected $api;
    protected $cdekShippingSettings;

    public function __construct()
    {
        $this->api = new CdekApi();
        $this->cdekShippingSettings = Helper::getSettingDataPlugin();
    }

    public function delete($orderId, $orderNumber)
    {
        $this->cleanMeta($orderId);

        $order = $this->api->getOrderByCdekNumber($orderNumber);
        $orderObj = json_decode($order);

        $validate = ValidateGetOrder::validate($orderObj, $orderNumber, $orderId);
        if (!$validate->state) {
            return $validate->response();
        }

        $delete = $this->api->deleteOrder($orderObj->entity->uuid);
        $delete = json_decode($delete);

        $validate = ValidateDeleteOrder::validate($delete, $orderNumber, $orderId);
        if (!$validate->state) {
            return $validate->response();
        }

        $validate = new Validate(true, 'Заказ удален.');
        return $validate->response();
    }

    /**
     * @param $orderId
     * @return void
     */
    protected function cleanMeta($orderId): void
    {
        $postOrderData = OrderMetaData::getMetaByOrderId($orderId);
        $postOrderData['cdek_order_uuid'] = '';
        $postOrderData['cdek_order_waybill'] = '';
        if (array_key_exists('order_number', $postOrderData)) {
            $postOrderData['order_number'] = '';
        }
        if (array_key_exists('order_uuid', $postOrderData)) {
            $postOrderData['order_uuid'] = '';
        }
        OrderMetaData::updateMetaByOrderId($orderId, $postOrderData);
    }
}