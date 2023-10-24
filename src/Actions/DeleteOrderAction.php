<?php

namespace Cdek\Actions;

use Cdek\CdekApi;
use Cdek\Model\OrderMetaData;
use Cdek\Model\Validate;
use Cdek\Validator\ValidateDeleteOrder;
use Cdek\Validator\ValidateGetOrder;

class DeleteOrderAction
{
    private CdekApi $api;

    public function __construct()
    {
        $this->api = new CdekApi;
    }
    public function __invoke(int $orderId): array {
        $orderNumber = OrderMetaData::getMetaByOrderId($orderId)['cdek_number'];

        OrderMetaData::cleanMetaByOrderId($orderId);

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

        $callCourier = new CallCourier();
        $callCourier->delete($orderId);

        $validate = new Validate(true, 'Заказ удален.');
        return $validate->response();
    }
}
