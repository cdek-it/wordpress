<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Controllers {

    use Cdek\Actions\IntakeCreateAction;
    use Cdek\Actions\IntakeDeleteAction;
    use Cdek\Blocks\AdminOrderBox;
    use Cdek\Config;
    use Cdek\Contracts\ExceptionContract;
    use Cdek\Exceptions\External\InvalidRequestException;
    use Cdek\Model\Order;
    use Cdek\Model\Tariff;
    use JsonException;

    class IntakeController
    {
        /**
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

            $val = rest_validate_object_value_from_schema($body, [
                'date'    => [
                    'description'       => esc_html__('Courier waiting date', 'cdekdelivery'),
                    'required'          => true,
                    'type'              => 'string',
                    'validate_callback' => static fn($param)
                        => checkdate(
                        (int)substr($param, 5, 2),
                        (int)substr($param, 8, 2),
                        (int)substr($param, 0, 4),
                    ),
                ],
                'from'    => [
                    'required'          => true,
                    'type'              => 'string',
                    'validate_callback' => static function ($param) {
                        $hours   = (int)substr($param, 0, 2);
                        $minutes = (int)substr($param, 3, 2);

                        return $hours >= 9 && $hours <= 19 && $minutes <= 59 && $minutes >= 0;
                    },
                ],
                'to'      => [
                    'required'          => true,
                    'type'              => 'string',
                    'validate_callback' => static function ($param) {
                        $hours   = (int)substr($param, 0, 2);
                        $minutes = (int)substr($param, 3, 2);

                        return $hours >= 12 && $hours <= 22 && $minutes <= 59 && $minutes >= 0;
                    },
                ],
                'comment' => [
                    'required' => false,
                    'type'     => 'string',
                ],
                'call'    => [
                    'required' => true,
                    'type'     => 'boolean',
                ],
            ], 'intake');

            if (is_wp_error($val)) {
                AdminOrderBox::createOrderMetaBox(
                    $order,
                    ['errors' => $val->get_error_messages()],
                );

                wp_die();
            }

            $shipping = $order->getShipping();
            $tariff   = $shipping !== null ? $shipping->tariff : null;

            if (Tariff::isFromOffice((int)($tariff ?: $order->tariff_id))) {
                $val = rest_validate_object_value_from_schema($body, [
                    'weight' => [
                        'required' => true,
                        'type'     => 'integer',
                    ],
                    'desc'   => [
                        'required' => true,
                        'type'     => 'string',
                    ],
                ], 'intake');

                if (is_wp_error($val)) {
                    AdminOrderBox::createOrderMetaBox(
                        $order,
                        ['errors' => $val->get_error_messages()],
                    );

                    wp_die();
                }
            }

            try {
                $result   = IntakeCreateAction::new()($order, $body);
                $messages = $result->state ? null : [$result->message];
            } catch (InvalidRequestException $e) {
                $messages = array_map(static fn(array $el) => $el['message'], $e->getData()['errors']);
            } catch (ExceptionContract $e) {
                $messages = [$e->getMessage()];
            }

            AdminOrderBox::createOrderMetaBox(
                $order,
                ['errors' => $messages],
            );

            wp_die();
        }

        /** @noinspection GlobalVariableUsageInspection */
        public static function delete(): void
        {
            check_ajax_referer(Config::DELIVERY_NAME);

            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !current_user_can('edit_posts')) {
                wp_die(-2, 403);
            }

            $id = (int)$_REQUEST['id'];

            $result = IntakeDeleteAction::new()($id);

            if ($result->state) {
                $meta = ['success' => [$result->message]];
            } else {
                $meta = ['errors' => [$result->message]];
            }

            AdminOrderBox::createOrderMetaBox($id, $meta);

            wp_die();
        }

        public function __invoke(): void
        {
            if (!wp_doing_ajax()) {
                return;
            }

            $prefix = Config::DELIVERY_NAME;

            add_action("wp_ajax_$prefix-intake_create", [__CLASS__, 'create']);
            add_action("wp_ajax_$prefix-intake_delete", [__CLASS__, 'delete']);
        }
    }
}
