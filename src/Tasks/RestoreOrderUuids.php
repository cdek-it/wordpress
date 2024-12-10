<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Tasks {

    use Cdek\Contracts\TaskContract;
    use Cdek\Exceptions\OrderNotFoundException;
    use Cdek\Exceptions\ScheduledTaskException;
    use Cdek\Model\Order;
    use Cdek\Model\TaskResult;
    use Iterator;

    class RestoreOrderUuids extends TaskContract
    {
        /**
         * @throws ScheduledTaskException
         */
        final protected function process(): Iterator
        {
            if ($this->taskMeta === null) {
                throw new ScheduledTaskException;
            }

            $failedOrders = [];

            foreach ($this->taskMeta as $order) {
                try {
                    $orderMeta       = new Order($order['external_id']);
                    $orderMeta->uuid = $order['id'];
                    $orderMeta->save();
                } catch (OrderNotFoundException $e) {
                    $failedOrders[] = $order['external_id'];
                }
            }

            if (empty($failedOrders)) {
                yield new TaskResult('success');
            } else {
                yield new TaskResult('warning', ['failed' => $failedOrders]);
            }
        }

        public static function getName(): string
        {
            return 'restore-order-uuids';
        }
    }
}
