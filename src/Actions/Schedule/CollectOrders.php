<?php

namespace Cdek\Actions\Schedule;

use Cdek\Contracts\TaskContract;
use Cdek\Model\CoreRequestData;
use Cdek\Model\Validate;

class CollectOrders extends TaskContract
{
    const ORDERS_LIMIT = 10000;
    private Validate $error;

    public static function getName(): string
    {
        return 'collect-orphaned-orders';
    }

    public function start()
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
                new CoreRequestData(
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
