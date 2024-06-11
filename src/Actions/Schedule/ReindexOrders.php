<?php


namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions\Schedule {

    use Cdek\Contracts\TaskContract;
    use Cdek\Exceptions\CdekScheduledTaskException;
    use Cdek\Model\CoreRequestData;
    use Cdek\Model\OrderMetaData;
    use Cdek\Model\Validate;

    class ReindexOrders extends TaskContract
    {
        private Validate $error;

        public function __construct(string $taskId)
        {
            parent::__construct($taskId);
            $this->initTaskData();
        }

        public static function getName(): string
        {
            return 'restore-order-uuids';
        }

        public function start()
        {
            if (empty($this->getTaskMeta())) {
                throw new CdekScheduledTaskException('[CDEKDelivery] Failed to get orders meta info',
                                               'cdek_error.core.data',
                                               $this->getTaskData()
                );
            }

            foreach ($this->getTaskMeta() as $arOrder) {
                OrderMetaData::updateMetaByOrderId(
                    $arOrder['external_id'],
                    [
                        'order_uuid'   => $arOrder['id']
                    ]
                );
            }

            $this->initData($this->cdekCoreApi->sendTaskData(
                $this->taskId,
                new CoreRequestData('success')
            ));
        }
    }
}
