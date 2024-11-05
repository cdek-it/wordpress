<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\CdekApi;
    use Cdek\Helpers\CheckoutHelper;
    use Cdek\MetaKeys;
    use Cdek\Model\CourierMetaData;
    use Cdek\Model\OrderMetaData;
    use Cdek\Model\Tariff;
    use Cdek\Model\ValidationResult;
    use Cdek\Note;
    use Cdek\Traits\CanBeCreated;
    use Cdek\Validator\ValidateCourier;
    use Cdek\Validator\ValidateCourierFormData;

    class IntakeCreateAction
    {
        use CanBeCreated;

        public CdekApi $api;

        public function __construct()
        {
            $this->api = new CdekApi();
        }

        public function __invoke(int $orderId, array $data): ValidationResult
        {
            $validate = ValidateCourierFormData::validate($data);
            if (!$validate->state) {
                return $validate;
            }

            $orderMetaData  = OrderMetaData::getMetaByOrderId($orderId);
            $shippingMethod = CheckoutHelper::getOrderShippingMethod(wc_get_order($orderId));

            $tariffId = $shippingMethod->get_meta(MetaKeys::TARIFF_CODE) ?:
                $shippingMethod->get_meta('tariff_code') ?: $orderMetaData['tariff_id'];

            if (Tariff::isTariffFromDoor($tariffId)) {
                $orderNumber = $orderMetaData['order_number'];
                $param       = $this->createRequestDataWithOrderNumber($data, $orderNumber);
            } else {
                $validate = ValidateCourierFormData::validatePackage($data);
                if (!$validate->state) {
                    return $validate;
                }

                $param = $this->createRequestData($data);
            }

            $courierObj = $this->api->callCourier($param);

            if (isset($courierObj['errors']) && $courierObj['errors'][0]['code'] === 'v2_intake_exists_by_order') {
                return new ValidationResult(
                    false, esc_html__(
                    'An error occurred while creating intake. Intake for this invoice already exists',
                    'cdekdelivery',
                ),
                );
            }

            sleep(5);

            $validate = ValidateCourier::validate($courierObj);
            if (!$validate->state) {
                return $validate;
            }

            $courierInfo = $this->api->courierInfo($courierObj['entity']['uuid']);

            sleep(5);

            $validate = ValidateCourier::validate($courierInfo);
            if (!$validate->state) {
                return $validate;
            }

            if (!isset($courierInfo['entity'])) {
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

            $intakeNumber = $courierInfo['entity']['intake_number'];

            CourierMetaData::addMetaByOrderId($orderId, [
                'courier_number' => $intakeNumber,
                'courier_uuid'   => $courierObj['entity']['uuid'],
                'not_cons'       => Tariff::isTariffFromDoor($tariffId),
            ]);

            $message
                = sprintf(
                esc_html__(/* translators: 1: number of intake 2: uuid of intake*/
                    'Intake has been created: Number: %1$s | Uuid: %2$s',
                    'cdekdelivery',
                ),
                $intakeNumber,
                $courierObj['entity']['uuid'],
            );
            Note::send($orderId, $message);

            return new ValidationResult(
                true, sprintf(
                esc_html__(/* translators: %s: uuid of application*/ 'Intake number: %s', 'cdekdelivery'),
                $intakeNumber,
            ),
            );
        }

        private function createRequestDataWithOrderNumber($data, $orderNumber)
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

        /**
         * @param $data
         *
         * @return array
         */
        protected function createRequestData($data): array
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
