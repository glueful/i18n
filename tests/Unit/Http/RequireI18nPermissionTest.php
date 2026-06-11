<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Tests\Unit\Http;

use Glueful\Auth\UserIdentity;
use Glueful\Extensions\I18n\Http\RequireI18nPermission;
use Glueful\Extensions\I18n\Tests\Support\FakePermissionManager;
use Glueful\Extensions\I18n\Tests\Support\I18nTestCase;
use Glueful\Permissions\PermissionManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireI18nPermissionTest extends I18nTestCase
{
    public function testPermissionMiddlewareReturns403WithoutAuthenticatedUser(): void
    {
        $response = (new RequireI18nPermission($this->appContext()))->handle(Request::create('/'), static fn(): string => 'next');
        self::assertSame(403, $response->getStatusCode());
    }

    public function testPermissionMiddlewareReturns403WhenManagerUnavailable(): void
    {
        $request = Request::create('/');
        $request->attributes->set('auth.user', new UserIdentity('user-1'));
        $response = (new RequireI18nPermission($this->appContext()))->handle($request, static fn(): string => 'next');
        self::assertSame(403, $response->getStatusCode());
    }

    public function testPermissionMiddlewareReturns403WithRealManagerAndNoProvider(): void
    {
        $manager = new PermissionManager();
        $manager->clearProvider();
        $this->bind(PermissionManager::class, $manager);
        $request = Request::create('/');
        $request->attributes->set('auth.user', new UserIdentity('user-1'));

        $response = (new RequireI18nPermission($this->appContext()))->handle($request, static fn(): string => 'next');
        self::assertSame(403, $response->getStatusCode());
    }

    public function testPermissionMiddlewareReturns403WhenPermissionDenied(): void
    {
        $this->bind(PermissionManager::class, new FakePermissionManager(false));
        $request = Request::create('/');
        $request->attributes->set('auth.user', new UserIdentity('user-1'));

        $response = (new RequireI18nPermission($this->appContext()))->handle($request, static fn(): string => 'next');
        self::assertSame(403, $response->getStatusCode());
    }

    public function testPermissionMiddlewareCallsNextOnlyWhenAllowed(): void
    {
        $manager = new FakePermissionManager(true);
        $this->bind(PermissionManager::class, $manager);
        $request = Request::create('/');
        $request->attributes->set('auth.user', new UserIdentity('user-1', roles: ['admin']));
        $called = false;

        $response = (new RequireI18nPermission($this->appContext()))->handle(
            $request,
            function () use (&$called): Response {
                $called = true;
                return new Response('ok', 200);
            },
            'i18n.manage'
        );

        self::assertTrue($called);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['user-1', 'i18n.manage', 'i18n'], array_slice($manager->lastCall, 0, 3));
    }
}
