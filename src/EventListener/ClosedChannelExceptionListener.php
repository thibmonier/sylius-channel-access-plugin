<?php

declare(strict_types=1);

namespace Tmonier\SyliusChannelAccessPlugin\EventListener;

use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * The router rejects an unknown path before the access listener runs, and the error page of the
 * shop shows the menu of the catalogue: on a closed channel an anonymous visitor gets the same
 * refusal as for any other path outside the allow-list, not a 404 page.
 */
final readonly class ClosedChannelExceptionListener
{
    public function __construct(private VisitorAccessListener $accessListener)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest() || !$event->getThrowable() instanceof HttpExceptionInterface) {
            return;
        }

        $response = $this->accessListener->refusalFor($event->getRequest());
        if ($response instanceof \Symfony\Component\HttpFoundation\Response) {
            $event->setResponse($response);
        }
    }
}
