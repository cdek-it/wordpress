<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Tasks {

    use Cdek\Contracts\TaskContract;
    use Cdek\Helpers\Logger;
    use Cdek\Model\TaskResult;
    use Iterator;
    use WC_Order_Query;

    class CollectOrphanedOrders extends TaskContract
    {
        private const ORDERS_LIMIT = 1000;

        final protected function process(): Iterator
        {
            $query = new WC_Order_Query(
                [
                    'orderby'  => 'id',
                    'order'    => 'ASC',
                    'paginate' => true,
                    'limit'    => self::ORDERS_LIMIT,
                    'return'   => 'ids',
                ],
            );

            Logger::debug('Collect orphaned orders started');

            for ($page = 1, $maxPages = 1; $page <= $maxPages; $page++) {
                $query->set('page', $page);
                $result = $query->get_orders();

                $maxPages = $result->max_num_pages;
                $orders = array_map(
                    static fn($order) => (string)$order,
                    $result->orders,
                );

                Logger::debug(
                    "Collect orphaned start page {$page}",
                    [
                        'max_pages' => $maxPages,
                        'orders' => $orders,
                    ]
                );

                yield new TaskResult(
                    'success', [
                        'orders' => $orders,
                    ], $page, $maxPages,
                );
            }
        }

        public static function getName(): string
        {
            return 'collect-orphaned-orders';
        }
    }
}
