<?php
namespace Power;

use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\Console\Adapter\AdapterInterface;
use Zend\ModuleManager\Feature\ServiceProviderInterface;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\ModuleManager\Feature\ConsoleBannerProviderInterface;
use Zend\ModuleManager\Feature\ConsoleUsageProviderInterface;
use Zend\EventManager\StaticEventManager;

class Module implements ServiceProviderInterface, AutoloaderProviderInterface, ConfigProviderInterface, ConsoleBannerProviderInterface, ConsoleUsageProviderInterface {
	protected $ignore_init = false;
	
    public function onBootstrap(MvcEvent $e) {
        $eventManager        = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);
		
		$this->ignore_init = ($config['module_ignore_init'][__NAMESPACE__] === true) ? true : false;
    }

    /**
     * Implement to provide the confirmation for this module
     * 
     * @return Array
     * */
    public function getConfig() {
        $module_config = include __DIR__ . '/config/module.config.php';
		
		if($this->ignore_init === true) {
			if(!empty($this->menu_configs)) {
				foreach($this->menu_configs as $menu_field => $options) {
					if(is_string($options)) {
						$menu_field = $options;
						$options = array();
					}
					
					unset($module_config[$menu_field]);
				}
			}
		}
		
		return $module_config;
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
			'power power-iq import <type>' => 'Import Power IQ database',
			array('type', 'Data type (default: ALL, or DataCenter, Floor, Aisle, Rack, Pdu'),
		);
	}
}
?>
