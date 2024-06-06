<?php

namespace Cdek\Actions\Schedule;

use Cdek\CdekCoreApi;
use Cdek\Contracts\TaskContract;
use Cdek\Model\Validate;

class CollectOrders extends TaskContract
{
    const ORDERS_LIMIT = 10000;
    private Validate $error;

    public function __construct($taskId)
    {
        parent::__construct($taskId);
    }

    public static function getName(): string
    {
        return 'collect-orphaned-orders';
    }

    public static function init($taskId)
    {
        $reindexOrders = new self($taskId);
        $reindexOrders->start();
    }

    public function start()
    {
        $this->reportOrders();
    }

    protected function reportOrders()
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

            $this->cdekCoreApi->addPageHeaders($maxPages, $page);

            $this->sendTaskData(
                [
                    'status' => 'success',
                    'result' => [
                        'orders' => array_map(
                            static fn($order) => (string)$order,
                            $result->orders
                        )
                    ]
                ]
            );
        }
    }
}
