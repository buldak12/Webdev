<?php

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AdminActivityLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        $route = (string) $request->attributes->get('_route', '');
        $method = strtoupper($request->getMethod());

        // Track only admin panel mutating operations.
        if (!str_starts_with($route, 'admin_') || !in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        if ($response->getStatusCode() >= 400) {
            return;
        }

        $actor = $this->security->getUser();
        if (!$actor instanceof User) {
            return;
        }

        $payload = $request->request->all();
        unset($payload['_token'], $payload['password'], $payload['_password']);

        $log = new ActivityLog();
        $log->setActor($actor);
        $log->setRouteName($route);
        $log->setMethod($method);
        $log->setAction($this->humanizeAction($route, $method));
        $log->setIpAddress($request->getClientIp());
        $log->setUserAgent(substr((string) $request->headers->get('User-Agent', ''), 0, 255));
        $log->setContext([
            'path' => $request->getPathInfo(),
            'payload' => $payload,
        ]);

        $this->em->persist($log);
        $this->em->flush();
    }

    private function humanizeAction(string $route, string $method): string
    {
        $label = str_replace('admin_', '', $route);
        $label = str_replace('_', ' ', $label);

        return sprintf('%s %s', $method, trim($label));
    }
}
