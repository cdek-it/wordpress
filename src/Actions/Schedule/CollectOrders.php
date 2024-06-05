<?php

namespace Cdek\Actions\Schedule;

use Cdek\CdekCoreApi;
use Cdek\Contracts\TaskContract;
use Cdek\Model\Validate;

class CollectOrders extends TaskContract
{
    const ORDERS_LIMIT = 10000;
    private CdekCoreApi $api;
    private array $orders;
    private Validate $error;

    public function __construct($taskId)
    {
        parent::__construct($taskId);
        $this->api = new CdekCoreApi();
    }

    public static function getName(): string
    {
        return 'collect-orphaned-orders';
    }

    public static function init($metaData = [])
    {
        if(empty($metaData['task_id'])){
            return;
        }
        $reindexOrders = new self($metaData['task_id']);
        $reindexOrders->start();
    }

    public function start()
    {
        $this->initOrders();
    }

    protected function initOrders()
    {
        $query = new \WC_Order_Query(
            [
                'orderby' => 'id',
                'order'   => 'ASC',
                'paginate'   => true,
                'limit' => self::ORDERS_LIMIT,
                'return'  => 'ids'
            ],
        );

        for ($page = 1, $maxPages = 1; $page <= $maxPages; $page++){
            $query->set('page', $page);
            $result = $query->get_orders();

            $maxPages = $result->max_num_pages;

            $this->addPageHeaders($maxPages, $page);

            $this->sendTaskData($result['orders']);
        }
    }
}
