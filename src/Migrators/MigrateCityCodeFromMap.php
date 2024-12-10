<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Migrators {

    use Cdek\CdekApi;
    use Cdek\ShippingMethod;
    use Exception;
    use JsonException;

    class MigrateCityCodeFromMap
    {
        private CdekApi $api;
        private ShippingMethod $shipping;

        final public function __invoke(?ShippingMethod $method = null): void
        {
            $this->shipping = $method ?: ShippingMethod::factory();
            $this->api      = new CdekApi($this->shipping);

            $this->migrateOffice();
            $this->migrateAddress();
        }

        private function migrateOffice(): void
        {
            $legacyOfficeData = $this->shipping->get_option('pvz_code');

            if (empty($legacyOfficeData)) {
                return;
            }

            try {
                $legacyOfficeData = json_decode($legacyOfficeData, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
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

            if (!empty($existingCity)) {
                return;
            }

            try {
                $cityInfo = $this->api->cityGet($city, $postal, $country);
            } catch (Exception $e) {
                return;
            }

            if ($cityInfo === null) {
                return;
            }

            $this->shipping->update_option('city_code', $cityInfo['code']);
            $this->shipping->update_option('city', $cityInfo['city']);
        }

        private function migrateAddress(): void
        {
            $legacyAddressData = $this->shipping->get_option('address');

            if (empty($legacyAddressData)) {
                return;
            }

            try {
                $parsedLegacyAddressData = json_decode($legacyAddressData, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                return;
            }

            $this->shipping->update_option('legacy_address', $legacyAddressData);

            if ($this->shipping->get_option('city_code') === null) {
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
