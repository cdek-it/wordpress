<?php


namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions\Schedule {

    use Cdek\CdekCoreApi;
    use Cdek\CdekShippingMethod;
    use Cdek\Model\OrderMetaData;
    use Cdek\Model\Validate;

    class ReindexOrders
    {
        private CdekCoreApi $api;
        private array $orders;
        private Validate $error;
        private array $responseOrders = [];

        public function __construct()
        {
            $this->api = new CdekCoreApi();
        }

        public static function initOrdersSend()
        {
            $reindex = new self();

            $reindex->run();

            status_header(200);

            exit("Server received '{$_REQUEST['data']}' from your browser.");
        }

        public static function getReindexOrders()
        {
            $reindex = new self();

            $reindex->checkReindexOrders();
            $reindex->writeReindexOrders();

        }

        public function run()
        {
            (new CdekShippingMethod())->update_option('cdek_start_reindex', 'Y');

            $this->getOrders();
            $this->exchangeOrders();

            if(isset($this->error)){

                status_header(500);

                exit($this->error->message);

            }

            if (false === as_has_scheduled_action('get_reindex_orders') ) {
                wp_schedule_single_event(
                    strtotime('tomorrow'),
                    'get_reindex_orders'
                );
            }
        }

        protected function checkReindexOrders()
        {
            $response = $this->api->checkUpdateOrders();
            $exchangeObj = json_decode($response, true);

            if(property_exists($exchangeObj, 'errors') || empty($exchangeObj['body']['orders'])){
                $this->error =
                    new Validate(
                        false,
                        __('An error occurred while creating request. Try again later', 'cdekdelivery'),
                    );

                return;
            }

            $this->responseOrders = $exchangeObj['body']['orders'];

            if(empty($response['body']['completed'])){
                wp_schedule_single_event(
                    time() + 60 * 5,
                    'get_reindex_orders'
                );
            }
        }

        protected function writeReindexOrders()
        {
            if(empty($this->responseOrders)){
                return;
            }

            $this->getOrders();

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
                        'order_uuid' => $responseOrder['order_uuid']
                    ]
                );
            }

        }

        protected function getOrders()
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

        protected function exchangeOrders()
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
}
