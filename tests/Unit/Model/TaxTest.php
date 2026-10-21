<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Model;

use Cdek\Model\Tax;
use Cdek\Tests\TestCase;
use Mockery;

final class TaxTest extends TestCase
{
    /**
     * `WC_Tax` не существует в тестовом окружении (WooCommerce не загружен)
     * мокается как несуществующий класс, аналогично `WC_Cart`/`WC_Product`
     * в CheckoutItemPriceActionTest.
     *
     * @param  mixed  $rates
     */
    private function mockRates(string $rateClass, $rates): void
    {
        Mockery::mock('alias:WC_Tax')
            ->shouldReceive('get_rates_for_tax_class')
            ->with($rateClass)
            ->andReturn($rates);
    }

    private function rate(string $taxRate): object
    {
        return (object)['tax_rate' => $taxRate];
    }

    public function testGetTaxReturnsNullWhenRatesIsNotArray(): void
    {
        $this->mockRates('standard', false);

        self::assertNull(Tax::getTax('standard'));
    }

    public function testGetTaxReturnsNullWhenRatesArrayIsEmpty(): void
    {
        $this->mockRates('standard', []);

        self::assertNull(Tax::getTax('standard'));
    }

    public function testGetTaxReturnsRoundedRateWhenSingleRateMatchesWhitelist(): void
    {
        $this->mockRates('standard', [$this->rate('20.0000')]);

        self::assertSame(20, Tax::getTax('standard'));
    }

    public function testGetTaxReturnsZeroRateAsIntZeroNotNull(): void
    {
        $this->mockRates('zero-rate', [$this->rate('0.0000')]);

        // 0 - валидный НДС из AVAILABLE_TAX, отличный от null ("ставка неизвестна")
        // важно проверить strict-типом, а не просто falsy-сравнением.
        self::assertSame(0, Tax::getTax('zero-rate'));
    }

    public function testGetTaxSumsAndRoundsMultipleRatesWhenWithinWhitelist(): void
    {
        // Несколько строк ставок (составной налог) суммируются перед округлением:
        // 12.3 + 7.6 = 19.9 -> round() -> 20, которое есть в AVAILABLE_TAX.
        $this->mockRates('standard', [$this->rate('12.3'), $this->rate('7.6')]);

        self::assertSame(20, Tax::getTax('standard'));
    }

    public function testGetTaxReturnsNullWhenRoundedSumIsNotInWhitelist(): void
    {
        // 15 - валидное число, но отсутствует в AVAILABLE_TAX ([null,0,5,10,12,20]).
        $this->mockRates('standard', [$this->rate('15.0000')]);

        self::assertNull(Tax::getTax('standard'));
    }
}
