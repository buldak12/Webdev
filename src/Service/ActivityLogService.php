<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class ActivityLogService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * Log an activity/action in the system
     */
    public function logActivity(
        ?User $actor,
        string $action,
        string $method = 'POST',
        ?string $routeName = null,
        ?array $context = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): ActivityLog {
        $log = new ActivityLog();
        $log->setActor($actor);
        $log->setAction($action);
        $log->setMethod($method);
        $log->setRouteName($routeName);
        $log->setContext($context ?? []);
        $log->setIpAddress($ipAddress);
        $log->setUserAgent($userAgent);

        $this->em->persist($log);
        $this->em->flush();

        return $log;
    }

    /**
     * Log activity from a Request object
     */
    public function logActivityFromRequest(
        ?User $actor,
        string $action,
        Request $request,
        ?string $routeName = null,
        ?array $context = null
    ): ActivityLog {
        return $this->logActivity(
            $actor,
            $action,
            $request->getMethod(),
            $routeName,
            $context,
            $this->getClientIp($request),
            $request->headers->get('User-Agent')
        );
    }

    /**
     * Get client IP from request
     */
    private function getClientIp(Request $request): string
    {
        // Check for IP set by proxy
        if ($request->headers->has('CF-Connecting-IP')) {
            return $request->headers->get('CF-Connecting-IP');
        }

        if ($request->headers->has('X-Forwarded-For')) {
            // X-Forwarded-For can contain multiple IPs
            $ips = explode(',', $request->headers->get('X-Forwarded-For'));
            return trim($ips[0]);
        }

        if ($request->headers->has('X-Real-IP')) {
            return $request->headers->get('X-Real-IP');
        }

        return $request->getClientIp() ?? '0.0.0.0';
    }
}
