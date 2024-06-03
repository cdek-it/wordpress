<?php


namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions\Schedule {

    use Cdek\CdekCoreApi;
    use Cdek\CdekShippingMethod;
    use Cdek\Contracts\TaskContract;
    use Cdek\Model\OrderMetaData;
    use Cdek\Model\Validate;

    class ReindexOrders extends TaskContract
    {
        private array $orders;
        private array $responseOrders = [];
        private Validate $error;

        public static function getName(): string
        {
            return 'restore-order-uuids';
        }

        public static function init($metaData = [])
        {
            if(empty($metaData)){
                return;
            }

            $reindexOrders = new self();
            $reindexOrders->setResponseOrders($metaData['orders']);
            $reindexOrders->start();
        }

        public function start()
        {
            if(empty($this->responseOrders)){
                return;
            }

            $this->initOrders();

            foreach ($this->orders as $orderId){
                $orderIndex = array_search($orderId, array_column($this->responseOrders, 'order_id'));

                if(empty($orderIndex)){
                    continue;
                }

                $responseOrder = $this->responseOrders[$orderIndex];

                OrderMetaData::updateMetaByOrderId(
                    $orderId,
                    [
                        'order_number' => $responseOrder['order_number'],
                        'order_uuid' => $responseOrder['order_uuid'],
                    ],
                );
            }
        }

        protected function initOrders()
        {
            $query = new \WC_Order_Query(
                [
                    'orderby' => 'id',
                    'order'   => 'ASC',
                    'return'  => 'ids',
                ],
            );

            foreach ($query->get_orders() as $orderId) {
                $this->orders[] = $orderId;
            }
        }

        public function setResponseOrders($orders)
        {
            $this->responseOrders = $orders;
        }
    }
}
