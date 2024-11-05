<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\CdekApi;
    use Cdek\Model\OrderMetaData;
    use Cdek\Model\ValidationResult;
    use Cdek\Traits\CanBeCreated;
    use Cdek\Validator\ValidateDeleteOrder;
    use Cdek\Validator\ValidateGetOrder;

    class OrderDeleteAction
    {
        use CanBeCreated;

        private CdekApi $api;

        public function __construct()
        {
            $this->api = new CdekApi;
        }

        public function __invoke(int $orderId): ValidationResult
        {
            $orderNumber = OrderMetaData::getMetaByOrderId($orderId)['order_uuid'];

            OrderMetaData::cleanMetaByOrderId($orderId);

            $order = $this->api->getOrder($orderNumber);

            $validate = ValidateGetOrder::validate($order, $orderNumber, $orderId);
            if (!$validate->state) {
                return $validate;
            }

            $delete = $this->api->deleteOrder($orderNumber);

            $validate = ValidateDeleteOrder::validate($delete, $orderNumber, $orderId);
            if (!$validate->state) {
                return $validate;
            }

            IntakeDeleteAction::new()($orderId);

            return new ValidationResult(true, esc_html__('Order has been deleted.', 'cdekdelivery'));
        }
    }
}
