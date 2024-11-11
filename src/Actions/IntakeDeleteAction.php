<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\CdekApi;
    use Cdek\Model\CourierMetaData;
    use Cdek\Model\ValidationResult;
    use Cdek\Note;
    use Cdek\Traits\CanBeCreated;
    use Cdek\Validator\ValidateCourier;

    class IntakeDeleteAction
    {
        use CanBeCreated;

        private CdekApi $api;

        public function __construct()
        {
            $this->api = new CdekApi();
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
        public function __invoke(int $orderId): ValidationResult
        {
            $courierMeta = CourierMetaData::getMetaByOrderId($orderId);

            if (empty($courierMeta)) {
                return new ValidationResult(true);
            }

            if ($courierMeta['courier_uuid'] === '') {
                CourierMetaData::cleanMetaByOrderId($orderId);

                return new ValidationResult(true);
            }

            $courierObj = $this->api->callCourierDelete($courierMeta['courier_uuid']);

            if (isset($courierObj['errors']) && $courierObj['errors'][0]['code'] === 'v2_entity_has_final_status') {
                return new ValidationResult(true);
            }

            $validate = ValidateCourier::validate($courierObj);
            if (!$validate->state) {
                return $validate;
            }

            CourierMetaData::cleanMetaByOrderId($orderId);

            $message = sprintf(
                esc_html__(/* translators: %s: request number */ 'Deleting a request to call a courier: %s',
                    'cdekdelivery',
                ),
                $courierObj['entity']['uuid'],
            );

            Note::send($orderId, $message);

            return new ValidationResult(true, esc_html__('Request has been deleted.', 'cdekdelivery'));
        }
    }
}
