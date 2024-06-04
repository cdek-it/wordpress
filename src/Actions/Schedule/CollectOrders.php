<?php

namespace Cdek\Actions\Schedule;

use Cdek\CdekCoreApi;
use Cdek\Contracts\TaskContract;
use Cdek\Model\Validate;

class CollectOrders extends TaskContract
{
    private CdekCoreApi $api;
    private string $taskId;
    private array $orders;
    private Validate $error;

    public function __construct($taskId)
    {
        $this->taskId = $taskId;
        $this->api = new CdekCoreApi();
    }

    public static function getName(): string
    {
        return 'collect_orphaned-orders';
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
        $this->exchangeOrders();
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

        $pagination = $this->getTaskMeta($this->taskId)['pagination'];

        if(!empty($pagination)){
            $query->set('page', $pagination['page']);
        }else{
            $pagination['page'] = 1;
        }

        $count = count($query->get_orders());

        $query->set('limit', self::ORDERS_LIMIT);

        $arOrders = $query->get_orders();

        foreach ($arOrders as $orderId) {
            $this->orders[] = $orderId;
        }

        $this->reportResult(
            ceil($count/self::ORDERS_LIMIT),
            $pagination['page']
        );
    }

    private function exchangeOrders()
    {
        $response = $this->api->reindexOrders($this->orders);
        $exchangeObj = json_decode($response, true);

        if (property_exists($exchangeObj, 'errors') || $exchangeObj['response']['code'] !== 202) {
            $this->error =
                new Validate(
                    false,
                    __('An error occurred while creating request. Try again later', 'cdekdelivery'),
                );
        }

    }
}
