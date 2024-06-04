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
        private string $taskId;
        private array $orders;
        private Validate $error;

        public function __construct(string $taskId)
        {
            $this->taskId = $taskId;
            $this->initTaskData($taskId);
        }

        public static function getName(): string
        {
            return 'restore-order-uuids';
        }

        public static function init($metaData = [])
        {
            if(empty($metaData['task_id'])){
                return;
            }

            $reindexOrders = new static($metaData['task_id']);
            $reindexOrders->start();
        }

        public function start()
        {
            if(empty($this->getTaskMeta($this->taskId)['orders'])){
                return;
            }

            $this->initOrders();

            foreach ($this->orders as $orderId){
                $orderIndex = array_search($orderId, array_column($this->getTaskMeta($this->taskId)['orders'], 'order_id'));

                if(empty($orderIndex)){
                    continue;
                }

                $responseOrder = $this->getTaskMeta($this->taskId)['orders'][$orderIndex];

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
                ]
            );

            $pagination = $this->getTaskMeta($this->taskId)['pagination'];

            if(!empty($pagination)){
                $query->set('page', $pagination['page']);
            }

            $query->set('limit', self::ORDERS_LIMIT);

            foreach ($query->get_orders() as $orderId) {
                $this->orders[] = $orderId;
            }
        }
    }
}
