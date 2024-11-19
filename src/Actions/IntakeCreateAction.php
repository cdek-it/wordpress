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
    use Cdek\Validator\IntakeValidator;

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
        public function __invoke(int $orderId, array $data): ValidationResult
        {
            $validate = IntakeValidator::validate($data);
            if (!$validate->state) {
                return $validate;
            }

            $order    = new Order($orderId);
            $shipping = $order->getShipping();

            if ($shipping === null) {
                throw new ShippingNotFoundException;
            }

            $tariffId = $shipping->tariff ?: $order->tariff_id;

            if (Tariff::isFromDoor($tariffId)) {
                $orderNumber = $order->number;
                $param       = $this->createRequestDataWithOrderNumber($data, $orderNumber);
            } else {
                $validate = IntakeValidator::validatePackage($data);
                if (!$validate->state) {
                    return $validate;
                }

                $param = $this->createRequestData($data);
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
                $orderId,
                sprintf(
                    esc_html__(/* translators: 1: number of intake 2: uuid of intake*/
                        'Intake has been created: Number: %1$s | Uuid: %2$s',
                        'cdekdelivery',
                    ),
                    $courierInfo['entity']['intake_number'],
                    $courierInfo['uuid'],
                ),
            );

            return new ValidationResult(
                true, sprintf(
                esc_html__(/* translators: %s: uuid of application*/ 'Intake number: %s', 'cdekdelivery'),
                $courierInfo['intake_number'],
            ),
            );
        }

        private function createRequestDataWithOrderNumber(array $data, string $orderNumber): array
        {
            $param['cdek_number']      = $orderNumber;
            $param['intake_date']      = $data['date'];
            $param['intake_time_from'] = $data['starttime'];
            $param['intake_time_to']   = $data['endtime'];
            $param['comment']          = $data['comment'];
            $param['sender']           = [
                'name'   => $data['name'],
                'phones' => [
                    'number' => $data['phone'],
                ],
            ];
            $param['from_location']    = [
                'address' => $data['address'],
            ];
            if ($data['need_call'] === "true") {
                $param['need_call'] = true;
            } else {
                $param['need_call'] = false;
            }

            return $param;
        }

        private function createRequestData(array $data): array
        {
            $param['intake_date']      = $data['date'];
            $param['intake_time_from'] = $data['starttime'];
            $param['intake_time_to']   = $data['endtime'];
            $param['name']             = $data['desc'];
            $param['weight']           = $data['weight'];
            $param['length']           = $data['length'];
            $param['width']            = $data['width'];
            $param['height']           = $data['height'];
            $param['comment']          = $data['comment'];
            $param['sender']           = [
                'name'   => $data['name'],
                'phones' => [
                    'number' => $data['phone'],
                ],
            ];
            $param['from_location']    = [
                'address' => $data['address'],
            ];

            $param['need_call'] = $data['need_call'] === 'true';

            return $param;
        }
    }
}
