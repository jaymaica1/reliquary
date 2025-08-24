<?php

namespace App\EventListener;

use App\Service\NotificationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class AdminNotificationListener implements EventSubscriberInterface
{
    public function __construct(
        private NotificationService $notificationService,
        private Environment $twig
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        
        // Only add notifications for admin routes
        if (!str_starts_with($request->getPathInfo(), '/admin')) {
            return;
        }

        // Get notification counts
        $notifications = $this->notificationService->getAllNotificationCounts();
        
        // Add global variables to Twig
        $this->twig->addGlobal('notifications', $notifications);
    }
}