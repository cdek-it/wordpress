<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\CdekApi;
    use Cdek\Exceptions\ShippingNotFoundException;
    use Cdek\Model\Order;
    use Cdek\Model\Tariff;
    use Cdek\Model\ValidationResult;
    use Cdek\Note;
    use Cdek\Traits\CanBeCreated;
    use DateTimeImmutable;

    class IntakeCreateAction
    {
        use CanBeCreated;

        public CdekApi $api;

        public function __construct()
        {
            $this->api = new CdekApi();
        }

        /**
         * @throws \Cdek\Exceptions\External\ApiException
         * @throws \Cdek\Exceptions\External\LegacyAuthException
         * @throws \Cdek\Exceptions\ShippingNotFoundException
         * @throws \Cdek\Exceptions\OrderNotFoundException
         */
        public function __invoke(Order $order, array $data): ValidationResult
        {
            $shipping = $order->getShipping();

            if ($shipping === null) {
                throw new ShippingNotFoundException;
            }

            $method = $shipping->getMethod();

            $param = [
                'need_call'        => $data['call'] === 'true',
                'intake_date'      => (new DateTimeImmutable($data['date']))->format('Y-m-d'),
                'intake_time_from' => $data['from'],
                'intake_time_to'   => $data['to'],
                'comment'          => $data['comment'] ?? null,
            ];

            if (Tariff::isFromDoor((int)($shipping->tariff ?: $order->tariff_id))) {
                $param['cdek_number'] = $order->number;
            } else {
                $param['name']          = $data['desc'];
                $param['weight']        = $data['weight'];
                $param['from_location'] = [
                    'address' => $method->address,
                    'code'    => $method->city_code,
                ];
                $param['sender']        = [
                    'name'   => $method->seller_name,
                    'phones' => [
                        'number' => $method->seller_phone,
                    ],
                ];
            }

            $result = $this->api->intakeCreate($param);

            if ($result->error() !== null && $result->error()['code'] === 'v2_intake_exists_by_order') {
                return new ValidationResult(
                    false, esc_html__(
                    'An error occurred while creating intake. Intake for this invoice already exists',
                    'cdekdelivery',
                ),
                );
            }

            if (!$result->missInvalidLegacyRequest()) {
                return new ValidationResult(
                    false, sprintf(/* translators: %s: Error message */ esc_html__(
                    'Error. The courier request has not been created. (%s)',
                    'cdekdelivery',
                ),
                    $result->error()['message'],
                ),
                );
            }

            sleep(5);

            $courierInfo = $this->api->intakeGet($result->entity()['uuid']);

            if (!$result->missInvalidLegacyRequest()) {
                return new ValidationResult(
                    false, sprintf(/* translators: %s: Error message */ esc_html__(
                    'Error. The courier request has not been created. (%s)',
                    'cdekdelivery',
                ),
                    $result->error()['message'],
                ),
                );
            }

            if ($courierInfo === null) {
                return new ValidationResult(
                    false, sprintf(
                    esc_html__(/* translators: %s: uuid of request*/
                        'Intake has been created, but an error occurred while obtaining its number. Intake uuid: %s',
                        'cdekdelivery',
                    ),
                    $courierInfo['requests'][0]['request_uuid'],
                ),
                );
            }

            $intake         = $order->getIntake();
            $intake->uuid   = $courierInfo['uuid'];
            $intake->number = $courierInfo['intake_number'];
            $intake->save();

            Note::send(
                $order->id,
                sprintf(
                    esc_html__(/* translators: 1: number of intake 2: uuid of intake*/
                        'Intake has been created: Number: %1$s | Uuid: %2$s',
                        'cdekdelivery',
                    ),
                    $courierInfo['intake_number'],
                    $courierInfo['uuid'],
                ),
            );

            return new ValidationResult(true);
        }
    }
}
