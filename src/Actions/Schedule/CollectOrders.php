<?php

namespace {
    defined('ABSPATH') or exit;
}

namespace Cdek\Actions\Schedule {

    use Cdek\Contracts\TaskContract;
    use Cdek\Model\TaskOutputData;

    class CollectOrders extends TaskContract
    {
        private const ORDERS_LIMIT = 1000;

        public static function getName(): string
        {
            return 'collect-orphaned-orders';
        }

        public function start(): void
        {
            $query = new \WC_Order_Query(
                [
                    'orderby'  => 'id',
                    'order'    => 'ASC',
                    'paginate' => true,
                    'limit'    => self::ORDERS_LIMIT,
                    'return'   => 'ids',
                ],
            );

            for ($page = 1, $maxPages = 1; $page <= $maxPages; $page++) {
                $query->set('page', $page);
                $result = $query->get_orders();

                $maxPages = $result->max_num_pages;

                $response = $this->cdekCoreApi->sendTaskData(
                    $this->taskId,
                    new TaskOutputData(
                        'success',
                        [
                            'orders' => array_map(
                                static fn($order) => (string)$order,
                                $result->orders,
                            )
                        ],
                        $page,
                        $maxPages
                    )
                );

                $this->initData($response);
            }
        }
    }
}
