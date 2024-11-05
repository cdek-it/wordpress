<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions\Schedule {

    use Cdek\Contracts\TaskContract;
    use Cdek\Exceptions\External\ApiException;
    use Cdek\Exceptions\ScheduledTaskException;
    use Cdek\Model\OrderMetaData;
    use Cdek\Model\TaskResult;
    use Iterator;

    class ReindexOrders extends TaskContract
    {
        /**
         * @return void
         * @throws ApiException
         * @throws ScheduledTaskException
         * @throws \JsonException
         */
        final protected function process(): Iterator
        {
            if ($this->taskMeta === null) {
                throw new ScheduledTaskException('Failed to get orders meta info');
            }

            foreach ($this->taskMeta as $order) {
                OrderMetaData::updateMetaByOrderId(
                    $order['external_id'],
                    ['order_uuid' => $order['id']],
                );
            }

            yield new TaskResult('success');
        }
    }
}
