<?php

declare(strict_types=1);

namespace Depthbomb\CsrfBundle\Tests\Integration;

use Depthbomb\CsrfBundle\CsrfBundle;
use Depthbomb\CsrfBundle\DependencyInjection\CsrfBundleExtension;
use Depthbomb\CsrfBundle\Tests\Fixtures\ClassProtectedController;
use Depthbomb\CsrfBundle\Tests\Fixtures\ProtectedController;
use Depthbomb\CsrfBundle\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Csrf\SameOriginCsrfTokenManager;

final class BundleTest extends TestCase
{
    public function testExtensionIsCreatedOnceAndKeepsItsAlias(): void
    {
        $bundle = new CsrfBundle();

        self::assertInstanceOf(CsrfBundleExtension::class, $bundle->getContainerExtension());
        self::assertSame($bundle->getContainerExtension(), $bundle->getContainerExtension());
        self::assertSame('csrf_bundle', $bundle->getContainerExtension()->getAlias());
        self::assertSame(realpath(__DIR__.'/../..'), realpath($bundle->getPath()));
    }

    public function testGeneratedSessionTokenReachesControllerAndDifferentSessionFails(): void
    {
        $kernel = new TestKernel();
        try {
            $kernel->boot();
            $request = $this->request();
            $request->setSession(new Session(new MockArraySessionStorage()));
            $stack = $kernel->getContainer()->get('request_stack');
            $stack->push($request);
            $token = $kernel->getContainer()->get('test.csrf_token_manager')->getToken('action')->getValue();
            $stack->pop();
            $request->request->set('_csrf_token', $token);

            $response = $kernel->handle($request);
            self::assertSame(200, $response->getStatusCode());
            self::assertSame('accepted', $response->getContent());

            $differentId = $this->request();
            $differentId->setSession($request->getSession());
            $differentId->attributes->set('_controller', new ClassProtectedController());
            $differentId->request->set('_csrf_token', $token);
            self::assertSame(412, $kernel->handle($differentId)->getStatusCode());

            $otherSession = $this->request();
            $otherSession->setSession(new Session(new MockArraySessionStorage()));
            $otherSession->request->set('_csrf_token', $token);
            self::assertSame(412, $kernel->handle($otherSession)->getStatusCode());
        } finally {
            $kernel->shutdown();
        }
    }

    #[DataProvider('rejectedRequests')]
    public function testKernelConvertsFailuresToHttpResponses(Request $request, int $status, int $type): void
    {
        $kernel = new TestKernel();
        $controller = new ProtectedController();
        $request->attributes->set('_controller', [$controller, 'action']);
        $request->setSession(new Session(new MockArraySessionStorage()));

        try {
            self::assertSame($status, $kernel->handle($request, $type)->getStatusCode());
            self::assertSame(0, $controller->calls);
        } finally {
            $kernel->shutdown();
        }
    }

    public static function rejectedRequests(): iterable
    {
        yield 'missing' => [new Request(), 428, HttpKernelInterface::MAIN_REQUEST];
        yield 'invalid' => [new Request(request: ['_csrf_token' => 'invalid']), 412, HttpKernelInterface::MAIN_REQUEST];
        yield 'zero is invalid rather than missing' => [new Request(request: ['_csrf_token' => '0']), 412, HttpKernelInterface::MAIN_REQUEST];
        yield 'invalid JSON' => [new Request(content: '{'), 400, HttpKernelInterface::MAIN_REQUEST];
        yield 'array token' => [new Request(request: ['_csrf_token' => ['invalid']]), 400, HttpKernelInterface::MAIN_REQUEST];
        yield 'subrequest missing' => [new Request(), 428, HttpKernelInterface::SUB_REQUEST];
    }

    public function testHeaderOnlyControllerAcceptsNonJsonBody(): void
    {
        $kernel = new TestKernel();
        try {
            $kernel->boot();
            $controller = new ProtectedController();
            $request = Request::create('https://example.com/action', 'POST', content: '<document/>');
            $request->headers->set('Content-Type', 'application/xml');
            $request->attributes->set('_controller', [$controller, 'headerOnly']);
            $request->setSession(new Session(new MockArraySessionStorage()));
            $stack = $kernel->getContainer()->get('request_stack');
            $stack->push($request);
            $token = $kernel->getContainer()->get('test.csrf_token_manager')->getToken('action')->getValue();
            $stack->pop();
            $request->headers->set('X-Token', $token);

            self::assertSame(200, $kernel->handle($request)->getStatusCode());
            self::assertSame(1, $controller->calls);
            self::assertSame('<document/>', $request->getContent());
        } finally {
            $kernel->shutdown();
        }
    }

    #[DataProvider('origins')]
    public function testSymfonyStatelessTokenManager(string $origin, int $expectedStatus): void
    {
        if (!class_exists(SameOriginCsrfTokenManager::class)) {
            self::markTestSkipped('Stateless CSRF requires Symfony 7.2 or later.');
        }

        $kernel = new TestKernel(stateless: true);
        try {
            $kernel->boot();
            $request = $this->request();
            $request->headers->set('Origin', $origin);
            $request->request->set('_csrf_token', $kernel->getContainer()->get('test.csrf_token_manager')->getToken('action')->getValue());

            self::assertSame($expectedStatus, $kernel->handle($request)->getStatusCode());
            self::assertFalse($request->hasSession());
        } finally {
            $kernel->shutdown();
        }
    }

    public static function origins(): iterable
    {
        yield 'same origin' => ['https://example.com', 200];
        yield 'cross origin' => ['https://attacker.example', 412];
    }

    private function request(): Request
    {
        $request = Request::create('https://example.com/action', 'POST');
        $request->attributes->set('_controller', [new ProtectedController(), 'action']);

        return $request;
    }
}
