<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Migrators {

    use Cdek\CdekApi;
    use Cdek\Helpers\Logger;
    use Cdek\ShippingMethod;
    use Exception;
    use JsonException;

    class MigrateCityCodeFromMap
    {
        private CdekApi $api;
        private ShippingMethod $shipping;

        final public function __invoke(?ShippingMethod $method = null): void
        {
            Logger::debug('Migrate cityCode from map started');

            $this->shipping = $method ?: ShippingMethod::factory();
            $this->api      = new CdekApi($this->shipping);

            $this->migrateOffice();
            $this->migrateAddress();
        }

        private function migrateOffice(): void
        {
            Logger::debug('Migrate office started');

            $legacyOfficeData = $this->shipping->get_option('pvz_code');

            if (empty($legacyOfficeData)) {
                return;
            }

            try {
                $legacyOfficeData = json_decode($legacyOfficeData, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                return;
            }

            if ( empty($legacyOfficeData['city']) || empty($legacyOfficeData['postal']) || empty($legacyOfficeData['country']) ) {
                Logger::debug('Legacy office data missed', ['data' => $legacyOfficeData]);
                return;
            }

            $this->exchangeCityCode(
                $legacyOfficeData['city'],
                $legacyOfficeData['postal'],
                $legacyOfficeData['country'],
            );
        }

        private function exchangeCityCode(string $city, string $postal, string $country): void
        {
            $existingCity = $this->shipping->get_option('city_code');

            Logger::debug('Exchange city code data', ['data' => $existingCity]);

            if (!empty($existingCity)) {
                return;
            }

            try {
                $cityInfo = $this->api->cityGet($city, $postal, $country);
            } catch (Exception $e) {
                Logger::debug('Exchange city get error', $e);

                return;
            }

            Logger::debug('Exchange city info', ['data' => $cityInfo]);

            if ($cityInfo === null) {
                return;
            }

            $this->shipping->update_option('city_code', $cityInfo['code']);
            $this->shipping->update_option('city', $cityInfo['city']);
        }

        private function migrateAddress(): void
        {
            $legacyAddressData = $this->shipping->get_option('address');

            Logger::debug('Legacy address data', ['data' => $legacyAddressData]);

            if (empty($legacyAddressData)) {
                return;
            }

            try {
                $parsedLegacyAddressData = json_decode($legacyAddressData, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                return;
            }

            $this->shipping->update_option('legacy_address', $legacyAddressData);

            if ( empty($parsedLegacyAddressData['city']) || empty($parsedLegacyAddressData['postal']) || empty($parsedLegacyAddressData['country']) ) {
                Logger::debug('Legacy address data missed', ['data' => $parsedLegacyAddressData]);
                return;
            }

            if( $this->shipping->get_option('city_code') === null ){
                $this->exchangeCityCode(
                    $parsedLegacyAddressData['city'],
                    $parsedLegacyAddressData['postal'],
                    $parsedLegacyAddressData['country'],
                );
            }

            $this->shipping->update_option('address', $parsedLegacyAddressData['address']);
        }
    }
}
