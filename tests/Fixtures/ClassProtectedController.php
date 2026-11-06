<?php

declare(strict_types=1);

namespace Depthbomb\CsrfBundle\Tests\Fixtures;

use Depthbomb\CsrfBundle\Attribute\CsrfProtected;
use Symfony\Component\HttpFoundation\Response;

#[CsrfProtected('controller')]
class ClassProtectedController
{
    public function __invoke(): Response
    {
        return new Response('accepted');
    }

    #[CsrfProtected('method')]
    public function action(): Response
    {
        return new Response('accepted');
    }
}
