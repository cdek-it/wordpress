<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Tasks {

    use Cdek\Contracts\TaskContract;
    use Cdek\Exceptions\OrderNotFoundException;
    use Cdek\Exceptions\ScheduledTaskException;
    use Cdek\Helpers\Logger;
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
            Logger::debug('Restore process started');

            if ($this->taskMeta === null) {
                Logger::debug('Restore order uuids task meta is empty');
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
                Logger::debug('Restore process success');

                yield new TaskResult('success');
            } else {
                Logger::debug('Restore process failed', ['failed' => $failedOrders]);

                yield new TaskResult('warning', ['failed' => $failedOrders]);
            }
        }

        public static function getName(): string
        {
            return 'restore-order-uuids';
        }
    }
}
