<?php

declare(strict_types=1);

namespace Depthbomb\CsrfBundle\Tests\Fixtures;

use Depthbomb\CsrfBundle\Attribute\CsrfProtected;
use Symfony\Component\HttpFoundation\Response;

class ProtectedController
{
    public int $calls = 0;

    #[CsrfProtected('action')]
    public function action(): Response
    {
        ++$this->calls;

        return new Response('accepted');
    }

    #[CsrfProtected('action', tokenField: null, queryParameter: null, header: 'X-Token')]
    public function headerOnly(): Response
    {
        ++$this->calls;

        return new Response('accepted');
    }

    #[CsrfProtected('action', tokenField: 'csrf', queryParameter: 'csrf', header: 'X-Token')]
    public function customSources(): Response
    {
        return new Response('accepted');
    }

    #[CsrfProtected('action', methods: ['post', 'DELETE'])]
    public function writeOnly(): Response
    {
        return new Response('accepted');
    }

    public function unprotected(): Response
    {
        return new Response('accepted');
    }

    #[CsrfProtected('action')]
    public static function staticAction(): Response
    {
        return new Response('accepted');
    }
}
