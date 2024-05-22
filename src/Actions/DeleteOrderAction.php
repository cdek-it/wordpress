<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

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

        public function __invoke(int $orderId): array
        {
            $orderNumber = OrderMetaData::getMetaByOrderId($orderId)['order_uuid'];

            OrderMetaData::cleanMetaByOrderId($orderId);

            $order = $this->api->getOrder($orderNumber);
            $orderObj = json_decode($order);

            $validate = ValidateGetOrder::validate($orderObj, $orderNumber, $orderId);
            if (!$validate->state) {
                return $validate->response();
            }

            $delete = $this->api->deleteOrder($orderNumber);
            $delete = json_decode($delete);

            $validate = ValidateDeleteOrder::validate($delete, $orderNumber, $orderId);
            if (!$validate->state) {
                return $validate->response();
            }

            $callCourier = new CallCourier();
            $callCourier->delete($orderId);

            $validate = new Validate(true, esc_html__('Order has been deleted.', 'cdekdelivery'));
            return $validate->response();
        }
    }
}
