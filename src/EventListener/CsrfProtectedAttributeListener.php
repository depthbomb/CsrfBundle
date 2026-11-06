<?php

declare(strict_types=1);

namespace Depthbomb\CsrfBundle\EventListener;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\HttpFoundation\Response;
use Depthbomb\CsrfBundle\Attribute\CsrfProtected;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author depthbomb
 * @since 1.0.0
 */
class CsrfProtectedAttributeListener implements EventSubscriberInterface
{
    public function __construct(private readonly CsrfTokenManagerInterface $tokenManager) {}

    public static function getSubscribedEvents(): array
    {
        return [ControllerEvent::class => ['onController', 512]];
    }

    public function onController(ControllerEvent $event): void
    {
        // Symfony returns class attributes before method attributes. Preserve that precedence.
        $attributes = $event->getAttributes();
        /** @var CsrfProtected|null $attribute */
        $attribute = $attributes[CsrfProtected::class][0] ?? null;
        if ($attribute === null)
        {
            return;
        }

        $request = $event->getRequest();
        if ($attribute->methods !== [] && !in_array($request->getMethod(), $attribute->methods, true))
        {
            return;
        }

        $token = $this->getTokenFromRequest($request, $attribute);
        if ($token === null || $token === '')
        {
            throw new HttpException(Response::HTTP_PRECONDITION_REQUIRED, 'Precondition Required');
        }

        if (!$this->tokenManager->isTokenValid(new CsrfToken($attribute->tokenId, $token)))
        {
            throw new HttpException(Response::HTTP_PRECONDITION_FAILED, 'Precondition Failed');
        }
    }

    private function getTokenFromRequest(Request $request, CsrfProtected $attribute): ?string
    {
        if ($attribute->tokenField !== null)
        {
            // Read parsed form data directly, without getPayload() cloning the bag.
            if ($request->request->has($attribute->tokenField))
            {
                return $request->request->getString($attribute->tokenField);
            }

            if ($request->request->count() === 0 && $request->getContent() !== '')
            {
                $payload = $request->getPayload();
                if ($payload->has($attribute->tokenField))
                {
                    return $payload->getString($attribute->tokenField);
                }
            }
        }

        if ($attribute->queryParameter !== null && $request->query->has($attribute->queryParameter))
        {
            return $request->query->getString($attribute->queryParameter);
        }

        return $attribute->header === null ? null : $request->headers->get($attribute->header);
    }
}
