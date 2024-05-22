<?php
namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class VersioningService
{
    private $requestStack;
    private $defaultVersion;
    public function __construct(RequestStack $requestStack, ParameterBagInterface $parameterBag)
    {
        $this->requestStack = $requestStack;
        $this->defaultVersion = $parameterBag->get('default_api_version');
    }


    /**
     * @return string
     */
    public function getVersion():string
    {
        $version=$this->defaultVersion;

        $reques=$this->requestStack->getCurrentRequest();
        $accpet=$reques->headers->get('Accept');
        $entete=explode(";",$accpet);

        foreach ($entete as $value) {
           if (strpos($value,'version') !== false) {
                $version=explode('=',$value) ;
                $version=$version[1];
                break;
           }
        }

        return $version;

    }
}