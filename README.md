# CsrfBundle

[![Downloads](https://img.shields.io/packagist/dt/depthbomb/csrf-bundle)](https://packagist.org/packages/depthbomb/csrf-bundle)

Protect Symfony controllers and actions with a `#[CsrfProtected]` attribute. Token generation and validation use Symfony's `CsrfTokenManagerInterface`, including its session and stateless implementations.

For new applications on Symfony 7.1 or later, use Symfony's built-in [`#[IsCsrfTokenValid]`](https://symfony.com/doc/current/security/csrf.html#checking-csrf-tokens-with-attributes). It covers the same controller-protection use case and is maintained with the framework.

CsrfBundle remains available for applications on Symfony 6.4 and for users who want its existing attribute, token-source priority, and distinct missing/invalid responses. This modernization keeps that API compatible with current PHP and Symfony releases. Further work is limited to compatibility, correctness, and maintenance; advanced CSRF features belong in Symfony's native integration.

## Installation

```console
composer require depthbomb/csrf-bundle
```

Register the bundle in `config/bundles.php` if it is not already registered:

```php
return [
    // Your other bundles...
    Depthbomb\CsrfBundle\CsrfBundle::class => ['all' => true],
];
```

Enable CSRF protection and sessions in your application's framework configuration:

```yaml
# config/packages/framework.yaml
framework:
    csrf_protection: true
    session: true
```

Applications supplying their own `CsrfTokenManagerInterface` implementation can use that service instead.

## Requirements and compatibility

The tested Symfony targets are the final 6.x release line, the current LTS, and the current stable release as of November 2026:

| Symfony | Target | Minimum PHP | CI runtimes |
| --- | --- | --- | --- |
| [6.4](https://symfony.com/releases/6.4) | Final 6.x line | 8.1 | 8.1, including lowest dependencies |
| [7.4](https://symfony.com/releases/7.4) | Current LTS | 8.2 | 8.2 and 8.3 |
| [8.1](https://symfony.com/releases/8.1) | Current stable | 8.4 | 8.4 and 8.5 |

Composer requirements are `^6.4 || ^7.4 || ^8.1`. These allow subsequent compatible minor releases within the same major; the table lists versions covered by CI. Composer selects components compatible with your PHP runtime. Use current security patches and a [supported PHP release](https://www.php.net/supported-versions.php) for production.

The bundle's runtime Symfony dependencies are installed automatically. PHPUnit, PHPStan, and FrameworkBundle are development dependencies only.

## Upgrading from 1.1

Symfony 6.3, 7.0-7.3, and 8.0 are no longer supported. Upgrade to Symfony 6.4, 7.4, or 8.1 before installing this update. The raised dependency minimums are a breaking compatibility change, even though the existing attribute API remains available.

On supported versions, existing `#[CsrfProtected('token-id')]` declarations keep their behavior. Token-source names, source priority, class-level precedence, HTTP methods, subrequest checks, and 428/412 responses keep the same defaults. The new source and method options are optional.

The token value `"0"` is now validated instead of being classified as missing. An invalid `"0"` therefore returns 412 instead of 428. Runtime Symfony dependencies are now declared correctly in Composer, and the bundle reports its package root for resource discovery.

## Usage

The first argument is the token ID, which must match the ID used to generate the token:

```php
<?php

namespace App\Controller;

use Depthbomb\CsrfBundle\Attribute\CsrfProtected;
use Symfony\Component\HttpFoundation\Response;

class ProfileController
{
    #[CsrfProtected('update-profile')]
    public function update(): Response
    {
        // Update the profile after the token check succeeds.
        return new Response('Profile updated');
    }
}
```

Put `#[CsrfProtected('update-profile')]` on the controller class to protect every action. If both the class and method have this attribute, the **class attribute takes precedence**, matching previous releases. A method attribute does not override a class attribute.

By default, validation applies to **every HTTP method**, including GET, and to main requests and subrequests. Unannotated controllers are unaffected.

Generate a token by injecting `CsrfTokenManagerInterface` into your service/controller:

```php
$token = $tokenManager->getToken('update-profile')->getValue();
```

Or render it in a Twig form, when Symfony's Twig integration is installed:

```twig
<input type="hidden" name="_csrf_token" value="{{ csrf_token('update-profile') }}">
```

## Sending tokens with requests

The first present source wins, in this order:

1. `_csrf_token` in the form body or JSON payload.
2. `token` in the query string.
3. The `X-Csrf-Token` request header, matched without regard to case.

An empty or invalid higher-priority token never falls back to a lower-priority source. Route attributes are not token sources. Prefer a form field or header because query-string tokens can end up in URL logs, browser history, and referrers.

Validation runs on `kernel.controller` at priority `512`, before controller arguments are resolved and before the action runs. Other listeners at higher priorities run first. Listeners that replace controllers should do so before this check.

| Input | Result |
| --- | --- |
| Missing or empty token | HTTP 428, `Precondition Required` |
| Submitted token rejected by the manager | HTTP 412, `Precondition Failed` |
| Malformed JSON or an array/object in the selected token field | HTTP 400 |
| Valid token | The request continues |

The string `"0"` is a submitted token and is passed to the manager. It is not treated as missing.

## Custom token sources

Change source names with named arguments. Set a source to `null` to disable it:

```php
#[CsrfProtected('update-profile', tokenField: '_token', queryParameter: null)]
```

For an API that sends its token exclusively in a header:

```php
#[CsrfProtected(
    'update-profile',
    tokenField: null,
    queryParameter: null,
    header: 'X-CSRF-Token',
)]
```

This skips payload parsing entirely, so XML, binary uploads, and large JSON bodies can use header tokens. With the default payload source enabled, an unparsed non-empty body must decode as JSON, as in previous releases. A malformed body is rejected before query/header fallback. If Symfony already populated the form bag, that bag is used instead of decoding the raw body.

## Restricting HTTP methods

Use `methods` when an action handles both a read request and a state-changing request:

```php
#[CsrfProtected('update-profile', methods: ['POST', 'PUT', 'PATCH', 'DELETE'])]
```

Only the listed effective request methods are checked. Names are normalized to uppercase; Symfony's method override rules still apply. Supply each method as a separate array entry. Invalid method names are rejected as configuration errors. An empty array keeps the default of checking every method. Include every method that changes application state.

## Stateless CSRF protection

On Symfony 7.2 or later, configure Symfony's [stateless CSRF protection](https://symfony.com/doc/current/security/csrf.html#stateless-csrf-tokens) for the token IDs you use:

```yaml
framework:
    csrf_protection:
        stateless_token_ids: ['update-profile']
```

Continue generating and submitting the token using the same ID. The bundle delegates validation to the configured manager, so Symfony handles origin checks and its double-submit protection. The bundle still requires a submitted token. Symfony's optional stateless cookie/header protocol is separate from the source names configured on `CsrfProtected`.

## Development

```console
composer update
composer check
composer audit
```

`composer check` validates package metadata, runs PHPUnit tests, and runs PHPStan at its maximum level. Integration tests boot a real Symfony kernel and check session isolation, HTTP errors, and stateless validation. Test kernel files are created in the system temporary directory.

The committed lock file records the current development environment. When developing on a different PHP version, use `composer update` to resolve compatible tools and Symfony components. A consuming application's dependency resolution does not use this package's lock file.

## Migrating to Symfony's attribute

For a payload-only action on Symfony 7.1 or later, the corresponding native attribute is:

```php
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

#[IsCsrfTokenValid('update-profile', tokenKey: '_csrf_token')]
```

Enable Symfony's security integration and CSRF protection before replacing an attribute. Keep the token ID used when generating tokens. Replace the bundle attribute on each migrated action so that it is not checked twice.

On Symfony 7.4 and 8.x, a header-only action can use:

```php
#[IsCsrfTokenValid(
    'update-profile',
    tokenKey: 'X-Csrf-Token',
    tokenSource: IsCsrfTokenValid::SOURCE_HEADER,
)]
```

Check these behavior differences when migrating:

| Behavior | CsrfBundle | Symfony's native attribute |
| --- | --- | --- |
| Default payload field | `_csrf_token` | `_token`; configure `tokenKey` to keep your field |
| Multiple token sources | Separate names for body, query, and header | One `tokenKey` for the selected sources; adapt clients or choose one source |
| Class and method attributes | Class attribute takes precedence | Each applicable attribute is checked |
| Invalid or missing token | 412 or 428 | Symfony's security exception handling; verify your application's response |
| Execution | Before controller argument resolution | After controller argument resolution |

The native attribute's options vary by Symfony version. Refer to the documentation for your installed version and test missing, invalid, and valid tokens, as well as every HTTP method your action accepts.

Once all bundle attributes and service references have been removed, remove `CsrfBundle` from `config/bundles.php` and uninstall it:

```console
composer remove depthbomb/csrf-bundle
```
