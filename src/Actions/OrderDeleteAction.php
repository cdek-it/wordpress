<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\CdekApi;
    use Cdek\Exceptions\External\InvalidRequestException;
    use Cdek\Helpers\Logger;
    use Cdek\Model\Order;
    use Cdek\Model\ValidationResult;
    use Cdek\Note;
    use Cdek\Traits\CanBeCreated;

    class OrderDeleteAction
    {
        use CanBeCreated;

        private CdekApi $api;

        public function __construct()
        {
            $this->api = new CdekApi;
        }

        /**
         * @throws \Cdek\Exceptions\OrderNotFoundException
         * @throws \Cdek\Exceptions\External\ApiException
         * @throws \Cdek\Exceptions\External\LegacyAuthException
         */
        public function __invoke(int $orderId): ValidationResult
        {
            $order       = new Order($orderId);
            $orderNumber = $order->number;
            $orderUuid   = $order->uuid;

            $order->clean();

            try {
                $this->api->orderGet($orderUuid);
            } catch (InvalidRequestException $e) {
                Note::send(
                    $orderId,
                    sprintf(
                        esc_html__(/* translators: %s: Order number */
                            'An attempt to delete order number %s failed with an error. Order not found.',
                            'cdekdelivery',
                        ),
                        $orderNumber,
                    ),
                );

                return new ValidationResult(
                    false, sprintf(
                    esc_html__(/* translators: %s: Order number */
                        'An error occurred while deleting the order. Order number %s was not found',
                        'cdekdelivery',
                    ),
                    $orderNumber,
                ),
                );
            }

            try {
                $this->api->orderDelete($orderUuid);
            } catch (InvalidRequestException $e) {
                Logger::warning(
                    'Failed to delete order',
                    [Logger::EXCEPTION_CONTEXT => $e],
                );

                Note::send(
                    $orderId,
                    sprintf(
                        esc_html__(/* translators: %s: Order number */
                            'An attempt to delete order number %s failed with an error. Error code: %s',
                            'cdekdelivery',
                        ),
                        $orderNumber,
                        $e->getData()[0]['code'],
                    ),
                );

                return new ValidationResult(
                    false, sprintf(
                    esc_html__(/* translators: %s: Order number */
                        'An error occurred while deleting the order. Order number %s was not deleted',
                        'cdekdelivery',
                    ),
                    $orderNumber,
                ),
                );
            }

            IntakeDeleteAction::new()($orderId);

            return new ValidationResult(true, esc_html__('Waybill has been deleted', 'cdekdelivery'));
        }
    }
}
