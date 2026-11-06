<?php

declare(strict_types=1);

namespace Depthbomb\CsrfBundle\Tests\EventListener;

use Depthbomb\CsrfBundle\EventListener\CsrfProtectedAttributeListener;
use Depthbomb\CsrfBundle\Tests\Fixtures\ClassProtectedController;
use Depthbomb\CsrfBundle\Tests\Fixtures\ProtectedController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Exception\RequestExceptionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class CsrfProtectedAttributeListenerTest extends TestCase
{
    #[DataProvider('validRequests')]
    public function testExtractsTokens(Request $request, string $action, string $expectedToken): void
    {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->expects(self::once())->method('isTokenValid')->with(self::callback(
            static fn (CsrfToken $token): bool => $token->getId() === 'action' && $token->getValue() === $expectedToken,
        ))->willReturn(true);

        (new CsrfProtectedAttributeListener($manager))->onController($this->event($request, [new ProtectedController(), $action]));
    }

    public static function validRequests(): iterable
    {
        yield 'form beats query and header' => [new Request(['token' => 'query'], ['_csrf_token' => 'form'], server: ['HTTP_X_CSRF_TOKEN' => 'header']), 'action', 'form'];
        yield 'JSON beats query and header' => [new Request(['token' => 'query'], server: ['HTTP_X_CSRF_TOKEN' => 'header'], content: '{"_csrf_token":"json"}'), 'action', 'json'];
        yield 'query beats header' => [new Request(['token' => 'query'], server: ['HTTP_X_CSRF_TOKEN' => 'header']), 'action', 'query'];
        yield 'header' => [new Request(server: ['HTTP_X_CSRF_TOKEN' => 'header']), 'action', 'header'];
        yield 'zero string is submitted' => [new Request(request: ['_csrf_token' => '0']), 'action', '0'];
        yield 'numeric JSON retains scalar conversion' => [new Request(content: '{"_csrf_token":0}'), 'action', '0'];
        yield 'custom form name' => [new Request(request: ['csrf' => 'custom']), 'customSources', 'custom'];
        yield 'custom query name' => [new Request(['csrf' => 'custom']), 'customSources', 'custom'];
        yield 'custom header name' => [new Request(server: ['HTTP_X_TOKEN' => 'custom']), 'customSources', 'custom'];
        yield 'header-only ignores malformed body and query' => [new Request(['token' => ['invalid']], server: ['HTTP_X_TOKEN' => 'header'], content: '<xml/>'), 'headerOnly', 'header'];
        yield 'parsed form prevents JSON decoding' => [new Request(request: ['_csrf_token' => 'form'], content: 'not json'), 'action', 'form'];
        yield 'form without token falls back to header' => [new Request(request: ['other' => 'value'], server: ['HTTP_X_CSRF_TOKEN' => 'header'], content: 'not json'), 'action', 'header'];
        yield 'JSON without token falls back to header' => [new Request(server: ['HTTP_X_CSRF_TOKEN' => 'header'], content: '{"other":true}'), 'action', 'header'];
    }

    #[DataProvider('missingRequests')]
    public function testMissingTokensFailBeforeCallingManager(Request $request, string $action): void
    {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->expects(self::never())->method('isTokenValid');

        try {
            (new CsrfProtectedAttributeListener($manager))->onController($this->event($request, [new ProtectedController(), $action]));
            self::fail('Expected a missing-token exception.');
        } catch (HttpException $exception) {
            self::assertSame(428, $exception->getStatusCode());
            self::assertSame('Precondition Required', $exception->getMessage());
        }
    }

    public static function missingRequests(): iterable
    {
        yield 'absent' => [new Request(), 'action'];
        yield 'empty form does not fall back' => [new Request(['token' => 'valid'], ['_csrf_token' => '']), 'action'];
        yield 'null JSON does not fall back' => [new Request(['token' => 'valid'], content: '{"_csrf_token":null}'), 'action'];
        yield 'empty query does not fall back' => [new Request(['token' => ''], server: ['HTTP_X_CSRF_TOKEN' => 'valid']), 'action'];
        yield 'empty header' => [new Request(server: ['HTTP_X_CSRF_TOKEN' => '']), 'action'];
        yield 'disabled sources' => [new Request(['token' => 'valid'], ['_csrf_token' => 'valid'], server: ['HTTP_X_CSRF_TOKEN' => 'valid']), 'headerOnly'];
        yield 'route attributes are not a token source' => [new Request(attributes: ['_csrf_token' => 'valid', 'token' => 'valid']), 'action'];
    }

    #[DataProvider('invalidRequests')]
    public function testInvalidTokensReturn412(Request $request): void
    {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->expects(self::once())->method('isTokenValid')->willReturn(false);

        try {
            (new CsrfProtectedAttributeListener($manager))->onController($this->event($request));
            self::fail('Expected an invalid-token exception.');
        } catch (HttpException $exception) {
            self::assertSame(412, $exception->getStatusCode());
            self::assertSame('Precondition Failed', $exception->getMessage());
        }
    }

    public static function invalidRequests(): iterable
    {
        yield 'ordinary token' => [new Request(request: ['_csrf_token' => 'invalid'])];
        yield 'zero' => [new Request(request: ['_csrf_token' => '0'])];
        yield 'invalid form does not try a valid header' => [new Request(request: ['_csrf_token' => 'invalid'], server: ['HTTP_X_CSRF_TOKEN' => 'valid'])];
    }

    #[DataProvider('malformedRequests')]
    public function testMalformedInputsRemainBadRequests(Request $request): void
    {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->expects(self::never())->method('isTokenValid');
        $this->expectException(RequestExceptionInterface::class);

        (new CsrfProtectedAttributeListener($manager))->onController($this->event($request));
    }

    public static function malformedRequests(): iterable
    {
        yield 'array form token' => [new Request(request: ['_csrf_token' => ['token']])];
        yield 'array query token' => [new Request(['token' => ['token']])];
        yield 'object JSON token' => [new Request(content: '{"_csrf_token":{"nested":true}}')];
        yield 'invalid JSON cannot use header fallback' => [new Request(server: ['HTTP_X_CSRF_TOKEN' => 'valid'], content: '{')];
        yield 'scalar JSON' => [new Request(content: '42')];
    }

    public function testUnprotectedControllerDoesNotReadBodyOrValidateToken(): void
    {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->expects(self::never())->method('isTokenValid');
        $request = new Request(content: 'malformed body');

        (new CsrfProtectedAttributeListener($manager))->onController($this->event($request, [new ProtectedController(), 'unprotected']));
    }

    public function testExcludedMethodDoesNotParseTheBody(): void
    {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->expects(self::never())->method('isTokenValid');
        $request = Request::create('/action', 'GET', content: 'not JSON');

        (new CsrfProtectedAttributeListener($manager))->onController($this->event($request, [new ProtectedController(), 'writeOnly']));
    }

    public function testEffectiveOverriddenMethodIsChecked(): void
    {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->expects(self::once())->method('isTokenValid')->willReturn(true);
        $request = Request::create('/action', 'POST', ['_csrf_token' => 'valid'], server: ['HTTP_X_HTTP_METHOD_OVERRIDE' => 'DELETE']);

        (new CsrfProtectedAttributeListener($manager))->onController($this->event($request, [new ProtectedController(), 'writeOnly']));
    }

    public function testListenerDoesNotReuseTokenOrAttributeFromPreviousRequest(): void
    {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->expects(self::exactly(2))->method('isTokenValid')->willReturnCallback(static function (CsrfToken $token): bool {
            return ($token->getId() === 'action' && $token->getValue() === 'first')
                || ($token->getId() === 'controller' && $token->getValue() === 'second');
        });
        $listener = new CsrfProtectedAttributeListener($manager);
        $first = new Request(request: ['_csrf_token' => 'first', 'other' => ['nested' => true]]);
        $originalForm = $first->request->all();
        $listener->onController($this->event($first));
        $listener->onController($this->event(new Request(request: ['_csrf_token' => 'second']), new ClassProtectedController()));

        self::assertSame($originalForm, $first->request->all());
    }

    #[DataProvider('controllerForms')]
    public function testControllerFormsAndClassPrecedence(callable $controller, string $expectedId, int $requestType): void
    {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->expects(self::once())->method('isTokenValid')->with(self::callback(
            static fn (CsrfToken $token): bool => $token->getId() === $expectedId,
        ))->willReturn(true);
        $event = $this->event(new Request(request: ['_csrf_token' => 'valid']), $controller, $requestType);

        (new CsrfProtectedAttributeListener($manager))->onController($event);
    }

    public static function controllerForms(): iterable
    {
        yield 'class takes precedence over method' => [[new ClassProtectedController(), 'action'], 'controller', HttpKernelInterface::MAIN_REQUEST];
        yield 'invokable object' => [new ClassProtectedController(), 'controller', HttpKernelInterface::MAIN_REQUEST];
        yield 'static array callable' => [[ProtectedController::class, 'staticAction'], 'action', HttpKernelInterface::MAIN_REQUEST];
        yield 'static string callable' => [ProtectedController::class.'::staticAction', 'action', HttpKernelInterface::MAIN_REQUEST];
        yield 'first-class method callable' => [(new ProtectedController())->action(...), 'action', HttpKernelInterface::MAIN_REQUEST];
        yield 'subrequest stays protected' => [[new ProtectedController(), 'action'], 'action', HttpKernelInterface::SUB_REQUEST];
    }

    #[DataProvider('requestMethods')]
    public function testMethodFiltering(string $method, bool $validate): void
    {
        $request = new Request(request: ['_csrf_token' => 'valid']);
        $request->setMethod($method);
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->expects($validate ? self::once() : self::never())->method('isTokenValid')->willReturn(true);

        (new CsrfProtectedAttributeListener($manager))->onController($this->event($request, [new ProtectedController(), 'writeOnly']));
    }

    public static function requestMethods(): iterable
    {
        yield ['GET', false];
        yield ['HEAD', false];
        yield ['OPTIONS', false];
        yield ['POST', true];
        yield ['DELETE', true];
    }

    private function event(Request $request, ?callable $controller = null, int $requestType = HttpKernelInterface::MAIN_REQUEST): ControllerEvent
    {
        return new ControllerEvent($this->createStub(HttpKernelInterface::class), $controller ?? [new ProtectedController(), 'action'], $request, $requestType);
    }
}
