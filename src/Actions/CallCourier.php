<?php

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
    use Cdek\Model\Validate;
    use Cdek\Note;
    use Cdek\Validator\ValidateCourier;
    use Cdek\Validator\ValidateCourierFormData;

    class CallCourier
    {
        public $api;

        public function __construct()
        {
            $this->api = new CdekApi();
        }

        public function call(int $orderId, $data)
        {
            $validate = ValidateCourierFormData::validate($data);
            if (!$validate->state) {
                return $validate->response();
            }

            $orderMetaData = OrderMetaData::getMetaByOrderId($orderId);
            $shippingMethod = CheckoutHelper::getOrderShippingMethod(wc_get_order($orderId));

            $tariffId = $shippingMethod->get_meta(MetaKeys::TARIFF_CODE) ?: $shippingMethod->get_meta('tariff_code') ?: $orderMetaData['tariff_id'];

            if (Tariff::isTariffFromDoor($tariffId)) {
                $orderNumber = $orderMetaData['order_number'];
                $param = $this->createRequestDataWithOrderNumber($data, $orderNumber);
            } else {
                $validate = ValidateCourierFormData::validatePackage($data);
                if (!$validate->state) {
                    return $validate->response();
                }

                $param = $this->createRequestData($data);
            }

            $response = $this->api->callCourier($param);
            $courierObj = json_decode($response);

            if (property_exists($courierObj, 'errors') &&
                $courierObj->errors[0]->code === 'v2_intake_exists_by_order') {
                $validate =
                    new Validate(false,
                                 "Во время создания заявки произошла ошибка. Заявка на вызов курьера к данной накладной уже существует.");
                return $validate->response();
            }

            sleep(5);

            $validate = ValidateCourier::validate($courierObj);
            if (!$validate->state) {
                return $validate->response();
            }

            $courierInfoJson = $this->api->courierInfo($courierObj->entity->uuid);
            $courierInfo = json_decode($courierInfoJson);

            sleep(5);

            $validate = ValidateCourier::validate($courierInfo);
            if (!$validate->state) {
                return $validate->response();
            }

            if (!property_exists($courierInfo, 'entity')) {
                $validate =
                    new Validate(false,
                                 "Заявка создана, но при получении номера заявки произошла ошибка. Uuid запроса: " .
                                 $courierInfo->requests[0]->request_uuid);
                return $validate->response();
            }

            $intakeNumber = $courierInfo->entity->intake_number;

            CourierMetaData::addMetaByOrderId($orderId,
                                              [
                                                  'courier_number' => $intakeNumber,
                                                  'courier_uuid'   => $courierObj->entity->uuid,
                                                  'not_cons'       => Tariff::isTariffFromDoor($tariffId),
                                              ]);

            $message =
                'Создана заявка на вызов курьера: Номер: ' . $intakeNumber . ' | Uuid: ' . $courierObj->entity->uuid;
            Note::send($orderId, $message);

            $validate = new Validate(true, "Номер заявки: " . $intakeNumber);
            return $validate->response();
        }

        private function createRequestDataWithOrderNumber($data, $orderNumber)
        {
            $param['cdek_number'] = $orderNumber;
            $param['intake_date'] = $data['date'];
            $param['intake_time_from'] = $data['starttime'];
            $param['intake_time_to'] = $data['endtime'];
            $param['comment'] = $data['comment'];
            $param['sender'] = [
                'name'   => $data['name'],
                'phones' => [
                    'number' => $data['phone'],
                ],
            ];
            $param['from_location'] = [
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
            $param['intake_date'] = $data['date'];
            $param['intake_time_from'] = $data['starttime'];
            $param['intake_time_to'] = $data['endtime'];
            $param['name'] = $data['desc'];
            $param['weight'] = $data['weight'];
            $param['length'] = $data['length'];
            $param['width'] = $data['width'];
            $param['height'] = $data['height'];
            $param['comment'] = $data['comment'];
            $param['sender'] = [
                'name'   => $data['name'],
                'phones' => [
                    'number' => $data['phone'],
                ],
            ];
            $param['from_location'] = [
                'address' => $data['address'],
            ];

            $param['need_call'] = $data['need_call'] === 'true';

            return $param;
        }

        /**
         * Проверить существование uuid
         * Если его нет, зачистить кэш, прекратить удаление
         * Получить данные о заявки
         * Проверить ее существование
         * Если заявки нет, зачистить кэш, прекратить удаление
         * Если есть удалить заявку
         * Проверить удаление заявки
         * Если ошибка кинуть ошибку в примечание, очистить кэш
         * Если успешно вернуть тру
         */
        public function delete($orderId)
        {
            $courierMeta = CourierMetaData::getMetaByOrderId($orderId);

            if (empty($courierMeta)) {
                return true;
            }

            if ($courierMeta['courier_uuid'] === '') {
                CourierMetaData::cleanMetaByOrderId($orderId);
                $validate = new Validate(true);
                return $validate->response();
            }

            $response = $this->api->callCourierDelete($courierMeta['courier_uuid']);
            $courierObj = json_decode($response);

            if (property_exists($courierObj, 'errors') &&
                $courierObj->errors[0]->code === 'v2_entity_has_final_status') {
                $validate = new Validate(true);
                return $validate->response();
            }

            $validate = ValidateCourier::validate($courierObj);
            if (!$validate->state) {
                return $validate->response();
            }

            CourierMetaData::cleanMetaByOrderId($orderId);

            $message = 'Удаление заявки на вызов курьера: ' . $courierObj->entity->uuid;
            Note::send($orderId, $message);

            $validate = new Validate(true, 'Заявка удалена.');
            return $validate->response();
        }
    }
}
