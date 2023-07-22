<?php
namespace Api;

use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\Console\Adapter\AdapterInterface;
use Zend\ModuleManager\Feature\ServiceProviderInterface;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\ModuleManager\Feature\ConsoleBannerProviderInterface;
use Zend\ModuleManager\Feature\ConsoleUsageProviderInterface;
use Zend\EventManager\StaticEventManager;

use Zend\Console\Request as ConsoleRequest;

class Module implements ServiceProviderInterface, AutoloaderProviderInterface, ConfigProviderInterface, ConsoleBannerProviderInterface, ConsoleUsageProviderInterface {    	
	public function onBootstrap(MvcEvent $e) {
        $eventManager        = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);
    }

    /**
     * Implement to provide the confirmation for this module
     * 
     * @return Array
     * */
    public function getConfig() {
        return include __DIR__ . '/config/module.config.php';
    }

    /**
     * Implement to provide the configuration for the autoloader so that it can load the Controller Classes
     * 
     * @return Array
     * */
    public function getAutoloaderConfig() {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }
    
    /**
     * Implement to provide services that are specific to this module.  This configuration will be mixin
     * with the service_manager configuration in module.config.php
     * 
     * @return Array
     * */
    public function getServiceConfig() {
        return array(
            
        );
    }
    
    public function getConsoleBanner(AdapterInterface $console) {
		
	}
	
	public function getConsoleUsage(AdapterInterface $console) {
		return array(
		);
	}
}
?>
