<?php

namespace App\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ExceptionSubscriber implements EventSubscriberInterface
{
    public function onKernelException(ExceptionEvent $event): void
    {
       $exception=$event->getThrowable(); // recuperation de l'exception

       //On verifie si l'exception est de type http ou pas
       //dans tous les cas on recupere le message de  l'esxception et le stat code approprié
       if ($exception instanceof HttpException) {
            $data=[
            'status'=>$exception->getStatusCode(),
            'message'=>$exception->getMessage()
            ];
            $event->setResponse( new JsonResponse($data));
       }else{
        $data=[
            'status'=>500,// Le status n'existe pas car ce n'est pas une exception HTTP, donc on met 500 par défaut.
            'message'=>$exception->getMessage()
            ];
            $event->setResponse( new JsonResponse($data));

       }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }
}
