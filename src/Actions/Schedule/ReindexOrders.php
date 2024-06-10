<?php


namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions\Schedule {

    use Cdek\Contracts\TaskContract;
    use Cdek\Model\OrderMetaData;
    use Cdek\Model\Validate;

    class ReindexOrders extends TaskContract
    {
        private array $orders = [];
        private Validate $error;

        public function __construct(string $taskId)
        {
            parent::__construct($taskId);
            $this->initTaskData();
        }

        public static function getName(): string
        {
            return 'restore-order-uuids';
        }

        public function start()
        {
            if (empty($this->getTaskMeta())) {
                return;
            }

            $this->initOrders();

            foreach ($this->orders as $orderId) {
                $orderIndex = array_search($orderId, array_column($this->getTaskMeta(), 'external_id'));

                $responseOrder = $this->getTaskMeta()[$orderIndex];

                OrderMetaData::updateMetaByOrderId(
                    $orderId,
                    [
                        'order_number' => $responseOrder['external_id'],
                        'order_uuid'   => $responseOrder['id'],
                    ],
                );
            }

            $this->sendTaskData(
                [
                    'status' => 'success',
                ],
            );
        }

        protected function initOrders()
        {
            $query = new \WC_Order_Query(
                [
                    'orderby'  => 'id',
                    'order'    => 'ASC',
                    'return'   => 'ids',
                    'post__in' => array_column($this->getTaskMeta(), 'external_id'),
                ],
            );

            foreach ($query->get_orders() as $orderId) {
                $this->orders[] = $orderId;
            }
        }
    }
}
