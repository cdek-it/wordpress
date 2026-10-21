<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Model;

use Cdek\Model\Service;
use Cdek\ShippingMethod;
use Cdek\Tests\TestCase;
use Mockery;

final class ServiceTest extends TestCase
{
    private function mockShippingMethod(
        bool $banAttachmentInspection = false,
        bool $tryingOn = false,
        bool $partialDelivery = false
    ): ShippingMethod {
        $shipping                                       = Mockery::mock('alias:' . ShippingMethod::class);
        $shipping->services_ban_attachment_inspection = $banAttachmentInspection;
        $shipping->services_trying_on                 = $tryingOn;
        $shipping->services_part_deliv                = $partialDelivery;

        return $shipping;
    }

    public function testFactoryReturnsEmptyArrayForPickupTariff(): void
    {
        $shipping = $this->mockShippingMethod(true, true, true);

        self::assertSame([], Service::factory($shipping, 366));
    }

    public function testFactoryReturnsEmptyArrayForTariffNotAvailableForShops(): void
    {
        $shipping = $this->mockShippingMethod(true, true, true);

        self::assertSame([], Service::factory($shipping, 748));
    }

    public function testFactoryReturnsEmptyArrayWhenNoServicesEnabled(): void
    {
        $shipping = $this->mockShippingMethod(false, false, false);

        self::assertSame([], Service::factory($shipping, 139));
    }

    public function testFactoryReturnsBanAttachmentInspectionWhenOnlyThatServiceEnabled(): void
    {
        $shipping = $this->mockShippingMethod(true, false, false);

        self::assertSame(
            [['code' => 'BAN_ATTACHMENT_INSPECTION']],
            Service::factory($shipping, 139)
        );
    }

    public function testFactoryIgnoresBanAttachmentInspectionWhenTryingOnAlsoEnabled(): void
    {
        $shipping = $this->mockShippingMethod(true, true, false);

        self::assertSame([], Service::factory($shipping, 139));
    }

    public function testFactoryIgnoresBanAttachmentInspectionWhenPartialDeliveryAlsoEnabled(): void
    {
        $shipping = $this->mockShippingMethod(true, false, true);

        self::assertSame([], Service::factory($shipping, 139));
    }

    public function testFactoryReturnsTryingOnWhenBanAttachmentInspectionDisabled(): void
    {
        $shipping = $this->mockShippingMethod(false, true, false);

        self::assertSame(
            [['code' => 'TRYING_ON']],
            Service::factory($shipping, 139)
        );
    }

    public function testFactoryReturnsPartDelivWhenBanAttachmentInspectionDisabled(): void
    {
        $shipping = $this->mockShippingMethod(false, false, true);

        self::assertSame(
            [['code' => 'PART_DELIV']],
            Service::factory($shipping, 139)
        );
    }

    public function testFactoryReturnsBothTryingOnAndPartDelivWhenBothEnabled(): void
    {
        $shipping = $this->mockShippingMethod(false, true, true);

        self::assertSame(
            [
                ['code' => 'TRYING_ON'],
                ['code' => 'PART_DELIV'],
            ],
            Service::factory($shipping, 139)
        );
    }
}
