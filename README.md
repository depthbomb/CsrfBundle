# CsrfBundle

[![Downloads](https://img.shields.io/packagist/dt/depthbomb/csrf-bundle)](https://packagist.org/packages/depthbomb/csrf-bundle)

This is a simple bundle built on top of Symfony's security package that allows you to easily protect any controller or individual controller action with a CSRF token check.

## Installation

```console
$ composer require depthbomb/csrf-bundle
```

## Requirements

- PHP >= 8.1
- Symfony 6.3.x

## Usage

_CsrfBundle_ uses attributes to protect controllers or controller methods. These attributes take a single string argument representing the ID of the CSRF token that will be validated on request.

```php
<?php namespace App\Controller

// ...
use Depthbomb\CsrfBundle\Attribute\CsrfProtected;

#[CsrfProtected('my token')] // protect entire controllers
class MyController extends AbstractController
{
    // ...

    #[CsrfProtected('my token')] // protect specific actions
    public function myAction(): Response
    {
        // ...
    }
}
```

You can then generate a CSRF token using your preferred method:

```php
// in a service/controller
$my_token = $this->tokenManager->getToken('my token');
```

```injectablephp
{# in Twig templates #}
{{ csrf_token('my token') }}
```

And that's it! Controllers/actions that are protected with the attribute are checked for token validity early in the event chain. When an action requires a token and one isn't provided (see below) then an `HttpException` is thrown with HTTP error code `428`. If a token is provided for a protected action and the token is invalid then an `HttpException` is thrown with a `412`.

## Sending tokens with requests

A token can be sent with a request in a few ways:

- Included in a `X-Csrf-Token` header
- Included in the request payload as `_csrf_token`
- Included as a `token` query string (`?token=xxx`)

---

> **Note**
> This is my first Symfony bundle (and Composer package). The bundle may or may not be implemented correctly as I had issues finding exactly the right and current way to create a bundle. Do let me know (via an issue or PR) if there is anything that should be done another way.
