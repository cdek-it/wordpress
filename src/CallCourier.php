<?php

namespace Cdek;
use Cdek\Model\CourierMetaData;
use Cdek\Model\Validate;
use Cdek\Validator\ValidateCourier;

class CallCourier
{
    public $api;
    public $adminSetting;

    public function __construct()
    {
        $this->api = new CdekApi();
        $this->adminSetting = Helper::getSettingDataPlugin();
    }

    public function call($data)
    {
        $param = $this->createRequestData($data);
        $response = $this->api->callCourier($param);
        $courierObj = json_decode($response);

        $validate = ValidateCourier::validate($courierObj);
        if (!$validate->state) {
            return $validate->response();
        }

        $courierInfoJson = $this->api->courierInfo($courierObj->entity->uuid);
        $courierInfo = json_decode($courierInfoJson);

        $validate = ValidateCourier::validate($courierInfo);
        if (!$validate->state) {
            return $validate->response();
        }

        $intakeNumber = $courierInfo->entity->intake_number;

        CourierMetaData::addMetaByOrderId($data['order_id'], ['courier_number' => $intakeNumber, 'courier_uuid' => $courierObj->entity->uuid]);

        $validate = new Validate(true, "Номер заявки: " . $intakeNumber);
        return $validate->response();
    }

    /**
     * @param $data
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
            'name' => $data['name'],
            'phones' => [
                'number' => $data['phone']
            ]
        ];
        $param['from_location'] = [
            'code' => $this->adminSetting['city_code_value'],
            'address' => $data['address']
        ];
        if ($data['need_call'] === "true") {
            $param['need_call'] = true;
        } else {
            $param['need_call'] = false;
        }
        return $param;
    }

    public function delete($orderId)
    {
        $courierMeta = CourierMetaData::getMetaByOrderId($orderId);
        $response = $this->api->callCourierDelete($courierMeta['courier_uuid']);
        $courierObj = json_decode($response);

        $validate = ValidateCourier::validate($courierObj);
        if (!$validate->state) {
            return $validate->response();
        }

        $message = 'Удаление заявки на вызов курьера: ' . $courierObj->entity->uuid;
        Note::send($orderId, $message);

        CourierMetaData::cleanMetaByOrderId($orderId);

        $validate = new Validate(true, 'Заявка удалена.');
        return $validate->response();
    }
}