<?php
namespace Auth\Controller\Plugin;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class SsoFactory implements FactoryInterface {
    public function createService(ServiceLocatorInterface $serviceLocator) {
        $services = $serviceLocator->getServiceLocator();
		$session = $services->get('session');
        $config = $services->get('Config');
		
		$loaders = array();
		foreach($config['sso']['loaders'] as $name => $cfgs) {
			$loaders[] = new \Els\Sso\User\DbLoader($services->get($cfgs['db']), $cfgs['select']);
		}
		
		$mloader = new \Els\Sso\User\MultipleLoader($loaders);
		return new \Auth\Controller\Plugin\Sso($config['sso']['session_field'], $config['sso']['content_field'], $session, $mloader);
    }
}
?>