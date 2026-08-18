<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Validator;

use Brain\Monkey\Functions;
use Cdek\Model\ValidationResult;
use Cdek\Tests\TestCase;
use Cdek\Validator\IntakeValidator;
use DateTime;

final class IntakeValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('esc_html__')->returnArg();
    }

    private function today(): string
    {
        return (new DateTime())->format('Y-m-d');
    }

    private function dateOffset(int $days): string
    {
        return date('Y-m-d', strtotime($this->today().sprintf('%+d days', $days)));
    }

    private function validIntakeData(array $overrides = []): array
    {
        return array_merge(
            [
                'date'      => $this->dateOffset(5),
                'starttime' => '10:00',
                'endtime'   => '18:00',
                'name'      => 'Иван Иванов',
                'phone'     => '+79991234567',
                'address'   => 'Москва, Тверская 1',
            ],
            $overrides,
        );
    }

    private function assertInvalid(ValidationResult $result, string $expectedMessage): void
    {
        self::assertFalse($result->state);
        self::assertSame($expectedMessage, $result->message);
    }

    public function testValidateReturnsValidResultForCompleteData(): void
    {
        $result = IntakeValidator::validate($this->validIntakeData());

        self::assertTrue($result->state);
        self::assertSame('', $result->message);
    }

    /**
     * @dataProvider emptyValueProvider
     */
    public function testValidateFailsWhenDateIsEmpty($emptyValue): void
    {
        $result = IntakeValidator::validate($this->validIntakeData(['date' => $emptyValue]));

        $this->assertInvalid($result, 'The courier waiting date has not been selected');
    }

    public static function emptyValueProvider(): array
    {
        return [
            'missing key'  => [null],
            'empty string' => [''],
            'zero string'  => ['0'],
        ];
    }

    public function testValidateFailsWhenDateIsBeforeToday(): void
    {
        $result = IntakeValidator::validate($this->validIntakeData(['date' => $this->dateOffset(-1)]));

        $this->assertInvalid($result, 'The courier waiting date cannot be earlier than the current date');
    }

    public function testValidatePassesWhenDateIsExactlyToday(): void
    {
        $result = IntakeValidator::validate($this->validIntakeData(['date' => $this->today()]));

        self::assertTrue($result->state);
    }

    public function testValidateFailsWhenDateIsMoreThan31DaysAhead(): void
    {
        $result = IntakeValidator::validate($this->validIntakeData(['date' => $this->dateOffset(32)]));

        $this->assertInvalid(
            $result,
            'The courier waiting date cannot be later than the 31st of the current date',
        );
    }

    public function testValidatePassesWhenDateIsExactly31DaysAhead(): void
    {
        $result = IntakeValidator::validate($this->validIntakeData(['date' => $this->dateOffset(31)]));

        self::assertTrue($result->state);
    }

    /**
     * @dataProvider missingWaitingTimeProvider
     */
    public function testValidateFailsWhenWaitingTimeIsIncomplete(array $overrides): void
    {
        $result = IntakeValidator::validate($this->validIntakeData($overrides));

        $this->assertInvalid($result, 'No courier waiting time selected');
    }

    public static function missingWaitingTimeProvider(): array
    {
        return [
            'no starttime' => [['starttime' => '']],
            'no endtime'   => [['endtime' => '']],
        ];
    }

    public function testValidateFailsWhenStartTimeIsAfterEndTime(): void
    {
        $result = IntakeValidator::validate(
            $this->validIntakeData(['starttime' => '18:00', 'endtime' => '10:00']),
        );

        $this->assertInvalid($result, 'The start of the courier waiting time cannot start later than the end time');
    }

    public function testValidateFailsWhenStartTimeEqualsEndTime(): void
    {
        $result = IntakeValidator::validate(
            $this->validIntakeData(['starttime' => '10:00', 'endtime' => '10:00']),
        );

        $this->assertInvalid($result, 'The start of the courier waiting time cannot start later than the end time');
    }

    public function testValidateFailsWhenNameIsEmpty(): void
    {
        $result = IntakeValidator::validate($this->validIntakeData(['name' => '']));

        $this->assertInvalid($result, 'Full name is required');
    }

    public function testValidateFailsWhenPhoneIsEmpty(): void
    {
        $result = IntakeValidator::validate($this->validIntakeData(['phone' => '']));

        $this->assertInvalid($result, 'Phone is required');
    }

    public function testValidateFailsWhenAddressIsEmpty(): void
    {
        $result = IntakeValidator::validate($this->validIntakeData(['address' => '']));

        $this->assertInvalid($result, 'Address is required');
    }

    private function validPackageData(array $overrides = []): array
    {
        return array_merge(
            [
                'desc'   => 'Коробка с товаром',
                'weight' => '1.5',
                'length' => '10',
                'width'  => '20',
                'height' => '30',
            ],
            $overrides,
        );
    }

    public function testValidatePackageReturnsValidResultForCompleteData(): void
    {
        $result = IntakeValidator::validatePackage($this->validPackageData());

        self::assertTrue($result->state);
        self::assertSame('', $result->message);
    }

    public function testValidatePackageFailsWhenDescIsEmpty(): void
    {
        $result = IntakeValidator::validatePackage($this->validPackageData(['desc' => '']));

        $this->assertInvalid($result, 'Cargo description is required');
    }

    public function testValidatePackageFailsWhenWeightIsEmpty(): void
    {
        $result = IntakeValidator::validatePackage($this->validPackageData(['weight' => '']));

        $this->assertInvalid($result, 'Weight is required');
    }

    public function testValidatePackageFailsWhenWeightIsNotNumeric(): void
    {
        $result = IntakeValidator::validatePackage($this->validPackageData(['weight' => 'abc']));

        $this->assertInvalid($result, 'Weight must be a number');
    }

    public function testValidatePackageFailsWhenLengthIsEmpty(): void
    {
        $result = IntakeValidator::validatePackage($this->validPackageData(['length' => '']));

        $this->assertInvalid($result, 'Length is required');
    }

    public function testValidatePackageFailsWhenLengthIsNotNumeric(): void
    {
        $result = IntakeValidator::validatePackage($this->validPackageData(['length' => 'abc']));

        $this->assertInvalid($result, 'Length must be a number');
    }

    public function testValidatePackageFailsWhenWidthIsEmpty(): void
    {
        $result = IntakeValidator::validatePackage($this->validPackageData(['width' => '']));

        $this->assertInvalid($result, 'Width is required');
    }

    public function testValidatePackageFailsWhenWidthIsNotNumeric(): void
    {
        $result = IntakeValidator::validatePackage($this->validPackageData(['width' => 'abc']));

        $this->assertInvalid($result, 'Width must be a number');
    }

    public function testValidatePackageFailsWhenHeightIsEmpty(): void
    {
        $result = IntakeValidator::validatePackage($this->validPackageData(['height' => '']));

        $this->assertInvalid($result, 'Height is required');
    }

    public function testValidatePackageFailsWhenHeightIsNotNumeric(): void
    {
        $result = IntakeValidator::validatePackage($this->validPackageData(['height' => 'abc']));

        $this->assertInvalid($result, 'Height must be a number');
    }
}
