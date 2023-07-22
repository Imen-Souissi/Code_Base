<?php
namespace Api\Service;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

abstract class AbstractService implements ServiceLocatorAwareInterface {
    private $serviceLocator;
    
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator) {
        $this->serviceLocator = $serviceLocator;
    }
    
    public function getServiceLocator() {
        return $this->serviceLocator;
    }
}
?>