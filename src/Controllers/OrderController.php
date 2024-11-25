<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Controllers {

    use Cdek\Actions\GenerateBarcodeAction;
    use Cdek\Actions\GenerateWaybillAction;
    use Cdek\Actions\OrderCreateAction;
    use Cdek\Actions\OrderDeleteAction;
    use Cdek\Blocks\AdminOrderBox;
    use Cdek\Config;
    use Cdek\Contracts\ExceptionContract;
    use Cdek\Exceptions\External\InvalidRequestException;
    use Cdek\Model\Order;
    use JsonException;

    class OrderController
    {
        /**
         * @throws \Cdek\Exceptions\External\ApiException
         * @throws \Cdek\Exceptions\External\LegacyAuthException
         * @throws \Cdek\Exceptions\OrderNotFoundException
         */
        public static function barcode(): void
        {
            check_ajax_referer(Config::DELIVERY_NAME);

            if (!current_user_can('edit_posts')) {
                wp_die(-2, 403);
            }

            /** @noinspection GlobalVariableUsageInspection */
            $result = GenerateBarcodeAction::new()((new Order((int)wp_unslash($_GET['id'])))->uuid);

            if ($result['success']) {
                wp_send_json_success($result['data']);
            }

            wp_send_json_error($result);
        }

        /**
         * @throws \Throwable
         * @noinspection GlobalVariableUsageInspection
         */
        public static function create(): void
        {
            check_ajax_referer(Config::DELIVERY_NAME);

            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !current_user_can('edit_posts')) {
                wp_die(-2, 403);
            }

            $id = (int)wp_unslash($_REQUEST['id']);

            try {
                $order = new Order($id);
            } catch (ExceptionContract $e) {
                AdminOrderBox::createOrderMetaBox(
                    $id,
                    ['errors' => [$e->getMessage()]],
                );

                wp_die();
            }

            try {
                $body = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                AdminOrderBox::createOrderMetaBox(
                    $order,
                    ['errors' => [$e->getMessage()]],
                );

                wp_die();
            }

            $val = rest_validate_array_value_from_schema($body, [
                'required' => true,
                'type'     => 'array',
                'minItems' => 1,
                'maxItems' => 255,
                'items'    => [
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => [
                        'length' => [
                            'required' => true,
                            'type'     => 'integer',
                        ],
                        'width'  => [
                            'required' => true,
                            'type'     => 'integer',
                        ],
                        'height' => [
                            'required' => true,
                            'type'     => 'integer',
                        ],
                        'items'  => [
                            'required' => false,
                            'type'     => 'array',
                            'minItems' => 1,
                            'items'    => [
                                'type'       => 'object',
                                'properties' => [
                                    'id'  => [
                                        'required' => true,
                                        'type'     => 'integer',
                                    ],
                                    'qty' => [
                                        'required' => true,
                                        'type'     => 'integer',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 'packages');

            if (is_wp_error($val)) {
                AdminOrderBox::createOrderMetaBox(
                    $order,
                    ['errors' => $val->get_error_messages()],
                );

                wp_die();
            }

            try {
                $result   = OrderCreateAction::new()($id, 0, $body);
                $messages = $result->state ? null : [$result->message];
            } catch (InvalidRequestException $e) {
                $messages = array_map(static fn(array $el) => $el['message'], $e->getData()['errors']);
            } catch (ExceptionContract $e) {
                $messages = [$e->getMessage()];
            }

            AdminOrderBox::createOrderMetaBox(
                $id,
                ['errors' => $messages],
            );

            wp_die();
        }

        /**
         * @throws \Cdek\Exceptions\External\LegacyAuthException
         * @throws \Cdek\Exceptions\External\ApiException
         * @throws \Cdek\Exceptions\OrderNotFoundException
         * @noinspection GlobalVariableUsageInspection
         */
        public static function delete(): void
        {
            check_ajax_referer(Config::DELIVERY_NAME);

            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !current_user_can('edit_posts')) {
                wp_die(-2, 403);
            }

            $id = (int)$_REQUEST['id'];

            $result = OrderDeleteAction::new()($id);

            if ($result->state) {
                $meta = ['success' => [$result->message]];
            } else {
                $meta = ['errors' => [$result->message]];
            }

            AdminOrderBox::createOrderMetaBox($id, $meta);

            wp_die();
        }

        /**
         * @throws \Cdek\Exceptions\External\ApiException
         * @throws \Cdek\Exceptions\External\LegacyAuthException
         * @throws \Cdek\Exceptions\OrderNotFoundException
         */
        public static function waybill(): void
        {
            check_ajax_referer(Config::DELIVERY_NAME);

            if (!current_user_can('edit_posts')) {
                wp_die(-2, 403);
            }

            try {
                /** @noinspection GlobalVariableUsageInspection */
                $result = GenerateWaybillAction::new()((new Order((int)wp_unslash($_GET['id'])))->uuid);

                if ($result['success']) {
                    wp_send_json_success($result['data']);
                }

                wp_send_json_error($result);
            } catch (ExceptionContract $e) {
                wp_send_json_error($e);
            }
        }

        public function __invoke(): void
        {
            if (!wp_doing_ajax()) {
                return;
            }

            $prefix = Config::DELIVERY_NAME;

            add_action("wp_ajax_$prefix-create", [__CLASS__, 'create']);
            add_action("wp_ajax_$prefix-delete", [__CLASS__, 'delete']);
            add_action("wp_ajax_$prefix-waybill", [__CLASS__, 'waybill']);
            add_action("wp_ajax_$prefix-barcode", [__CLASS__, 'barcode']);
        }
    }
}
