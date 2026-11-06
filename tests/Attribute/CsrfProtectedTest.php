<?php

declare(strict_types=1);

namespace Depthbomb\CsrfBundle\Tests\Attribute;

use Depthbomb\CsrfBundle\Attribute\CsrfProtected;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CsrfProtectedTest extends TestCase
{
    public function testExistingConstructorAndNamedArgumentRemainValid(): void
    {
        $attribute = new CsrfProtected(tokenId: 'action');

        self::assertSame('action', $attribute->tokenId);
        self::assertSame('_csrf_token', $attribute->tokenField);
        self::assertSame('token', $attribute->queryParameter);
        self::assertSame('X-Csrf-Token', $attribute->header);
        self::assertSame([], $attribute->methods);
    }

    public function testNormalizesMethodsWithoutDuplicates(): void
    {
        self::assertSame(['POST', 'DELETE'], (new CsrfProtected('action', methods: ['post', 'POST', 'delete']))->methods);
    }

    public function testAcceptsCustomHttpMethodTokens(): void
    {
        self::assertSame(['M-SEARCH', 'CUSTOM_METHOD'], (new CsrfProtected('action', methods: ['m-search', 'custom_method']))->methods);
    }

    #[DataProvider('invalidOptions')]
    public function testRejectsInvalidOptions(array $options): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CsrfProtected('action', ...$options);
    }

    public static function invalidOptions(): iterable
    {
        yield [['tokenField' => '']];
        yield [['queryParameter' => '']];
        yield [['header' => '']];
        yield [['methods' => ['']]];
        yield [['methods' => [' POST ']]];
        yield [['methods' => ['POST DELETE']]];
        yield [['methods' => ['POST,DELETE']]];
        yield [['methods' => ["PO\nST"]]];
        yield [['methods' => ["POST\0"]]];
        yield [['methods' => ['PÖST']]];
    }
}
