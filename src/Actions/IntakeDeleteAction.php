<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\CdekApi;
    use Cdek\Exceptions\External\InvalidRequestException;
    use Cdek\Model\Intake;
    use Cdek\Model\ValidationResult;
    use Cdek\Note;
    use Cdek\Traits\CanBeCreated;

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
            $courierMeta = new Intake($orderId);

            if (empty($courierMeta->uuid)) {
                $courierMeta->clean();

                return new ValidationResult(true);
            }

            try {
                $this->api->intakeDelete($courierMeta->uuid);
            } catch (InvalidRequestException $e) {
                if ($e->getData()[0]['code'] === 'v2_entity_has_final_status') {
                    return new ValidationResult(true);
                }

                return new ValidationResult(
                    false, sprintf(/* translators: %s: Error message */ esc_html__(
                    'Error. The courier request has not been created. (%s)',
                    'cdekdelivery',
                ),
                    $e->getData()[0]['message'],
                ),
                );
            }

            Note::send(
                $orderId,
                sprintf(
                    esc_html__(/* translators: %s: request number */ 'Intake %s has been deleted',
                        'cdekdelivery',
                    ),
                    $courierMeta->number,
                ),
            );

            $courierMeta->clean();

            return new ValidationResult(true, esc_html__('Intake has been deleted', 'cdekdelivery'));
        }
    }
}
