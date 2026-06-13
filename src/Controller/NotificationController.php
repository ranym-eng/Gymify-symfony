<?php

   namespace App\Controller;

   use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
   use Symfony\Component\HttpFoundation\JsonResponse;
   use Symfony\Component\Routing\Annotation\Route;

   class NotificationController extends AbstractController
   {
       #[Route('/notifications/queued', name: 'notifications_queued', methods: ['GET'])]
       public function getQueuedNotifications(): JsonResponse
       {
           $user = $this->getUser();
           if (!$user) {
               return new JsonResponse(['error' => 'User not authenticated'], 401);
           }

           $queueFile = 'var/notifications_queue.json';
           if (!file_exists($queueFile)) {
               return new JsonResponse([]);
           }

           $queue = json_decode(file_get_contents($queueFile), true);
           if (json_last_error() !== JSON_ERROR_NONE) {
               return new JsonResponse(['error' => 'Error reading notification queue'], 500);
           }

           $userNotifications = array_filter($queue, function ($notification) use ($user) {
               return $notification['userId'] == $user->getId();
           });

           return new JsonResponse(array_values($userNotifications));
       }
   }