<?php namespace Depthbomb\CsrfBundle\EventListener;

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
    private const CSRF_TOKEN_FIELD       = '_csrf_token';
    private const CSRF_TOKEN_QUERY_PARAM = 'token';
    private const CSRF_TOKEN_HEADER      = 'X-Csrf-Token';

    public function __construct(private readonly CsrfTokenManagerInterface $tokenManager) {}

    public static function getSubscribedEvents(): array
    {
        return [ControllerEvent::class => ['onController', 512]];
    }

    public function onController(ControllerEvent $event): void
    {
        $attributes = $event->getAttributes();

        /** @var ?CsrfProtected $token_attr */
        $token_attr = $attributes[CsrfProtected::class][0] ?? null;
        if (!$token_attr)
        {
            return;
        }

        $request  = $event->getRequest();
        $token    = $this->getTokenFromRequest($request);
        $token_id = $token_attr->tokenId;

        if (!$token)
        {
            $code = Response::HTTP_PRECONDITION_REQUIRED;
        }
        elseif (!$this->tokenManager->isTokenValid(new CsrfToken($token_id, $token)))
        {
            $code = Response::HTTP_PRECONDITION_FAILED;
        }

        if (isset($code))
        {
            throw new HttpException($code, Response::$statusTexts[$code]);
        }
    }

    private function getTokenFromRequest(Request $request): ?string
    {
        $payload = $request->getPayload();
        if ($payload->has($this::CSRF_TOKEN_FIELD))
        {
            return $payload->getString($this::CSRF_TOKEN_FIELD);
        }

        $query = $request->query;
        if ($query->has($this::CSRF_TOKEN_QUERY_PARAM))
        {
            return $query->getString($this::CSRF_TOKEN_QUERY_PARAM);
        }

        $headers = $request->headers;
        return $headers->get($this::CSRF_TOKEN_HEADER);
    }
}
