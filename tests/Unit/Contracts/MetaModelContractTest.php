<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Contracts;

use Cdek\Contracts\MetaModelContract;
use Cdek\Tests\TestCase;

/**
 * Тестовый дублёр `MetaModelContract` - абстрактный класс сам по себе не
 * инстанциируется, `save()`/`clean()` реализованы как минимальные заглушки
 * (со счётчиком вызовов у save(), чтобы проверить, что __get() дёргает его
 * только при миграции алиаса, а не на каждое чтение).
 */
final class MetaModelContractDouble extends MetaModelContract
{
    protected const ALIASES = [
        'foo' => ['legacy_foo', 'legacy_foo_2'],
    ];

    public int $saveCalls = 0;

    public function __construct(array $meta = [])
    {
        $this->meta = $meta;
    }

    public function save(): void
    {
        $this->saveCalls++;
    }

    public function clean(): void
    {
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function getDirty(): array
    {
        return $this->dirty;
    }
}

final class MetaModelContractTest extends TestCase
{
    public function testGetReturnsDirectMetaValueWhenKeyExists(): void
    {
        $model = new MetaModelContractDouble(['bar' => 'baz']);

        self::assertSame('baz', $model->bar);
        self::assertSame(0, $model->saveCalls);
    }

    public function testGetReturnsNullWhenKeyIsUnknownAndHasNoAlias(): void
    {
        $model = new MetaModelContractDouble([]);

        self::assertNull($model->unrelated_key);
        self::assertSame(0, $model->saveCalls);
    }

    public function testGetReturnsNullWhenAliasedKeyHasNoValueUnderAnyAlias(): void
    {
        $model = new MetaModelContractDouble([]);

        self::assertNull($model->foo);
        self::assertSame(0, $model->saveCalls);
    }

    public function testGetMigratesValueFromAliasMovesKeyAndCallsSave(): void
    {
        $model = new MetaModelContractDouble(['legacy_foo' => 'value']);

        self::assertSame('value', $model->foo);
        self::assertSame(['foo' => 'value'], $model->getMeta());
        self::assertSame(1, $model->saveCalls);
    }

    public function testGetFallsBackToNextAliasWhenFirstOneIsMissing(): void
    {
        $model = new MetaModelContractDouble(['legacy_foo_2' => 'from second alias']);

        self::assertSame('from second alias', $model->foo);
        self::assertSame(['foo' => 'from second alias'], $model->getMeta());
    }

    public function testGetPrefersFirstAliasInDeclarationOrderWhenBothPresent(): void
    {
        $model = new MetaModelContractDouble([
            'legacy_foo'   => 'first',
            'legacy_foo_2' => 'second',
        ]);

        self::assertSame('first', $model->foo);
        // Второй алиас так и остаётся нетронутым - резолвится только один, первый найденный.
        self::assertSame(['legacy_foo_2' => 'second', 'foo' => 'first'], $model->getMeta());
    }

    public function testSetStoresValueAndMarksKeyDirty(): void
    {
        $model = new MetaModelContractDouble();

        $model->bar = 'value';

        self::assertSame('value', $model->getMeta()['bar']);
        self::assertSame(['bar'], $model->getDirty());
    }

    public function testSetDoesNotDuplicateKeyInDirtyListOnRepeatedAssignment(): void
    {
        $model = new MetaModelContractDouble();

        $model->bar = 'first';
        $model->bar = 'second';

        self::assertSame(['bar'], $model->getDirty());
    }

    public function testIssetReflectsDirectMetaPresenceOnly(): void
    {
        $model = new MetaModelContractDouble(['bar' => 'value']);

        self::assertTrue(isset($model->bar));
        self::assertFalse(isset($model->unrelated_key));
        // __isset() не резолвит алиасы - значение лежит под legacy-ключом,
        // но по "новому" имени isset() всё равно скажет false.
        self::assertFalse(isset((new MetaModelContractDouble(['legacy_foo' => 'value']))->foo));
    }

    public function testUnsetRemovesKeyFromMeta(): void
    {
        $model = new MetaModelContractDouble(['bar' => 'value']);

        unset($model->bar);

        self::assertArrayNotHasKey('bar', $model->getMeta());
    }
}
