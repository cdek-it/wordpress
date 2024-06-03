<?php

namespace Cdek\Actions\Schedule;

use Cdek\CdekCoreApi;
use Cdek\Contracts\TaskContract;
use Cdek\Model\Validate;

class CollectOrders extends TaskContract
{
    private CdekCoreApi $api;
    private array $orders;
    private Validate $error;

    public function __construct()
    {
        $this->api = new CdekCoreApi();
    }

    public static function getName(): string
    {
        return 'collect_orphaned-orders';
    }

    public static function init($metaData = [])
    {
        $reindexOrders = new self();
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

        foreach ($query->get_orders() as $orderId) {
            $this->orders[] = $orderId;
        }
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
