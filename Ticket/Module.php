<?php
namespace Ticket;

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
			'ticket footprints import [--start=] [--end=] [--ticket=] [--round=]' => 'Import Footprints database',
			array('start', 'A specific start date/time (e.g. 2015-01-01 00:00:00; January 1, 2015; -1 week)'),
			array('end', 'A specific end date/time (e.g. 2015-02-01 00:00:00; February 1, 2015; +40 day)'),
			array('ticket', 'A specific ticket # to process'),
			array('round', 'Starting round to start processing (e.g. 0 => first round)'),
			array('perround', '# of tickets to process per round (defaults to 100)'),
			
			'ticket salesforce import [--start=] [--end=] [--osr=] [--sfdc-id=] [--round=]' => 'Import Salesforce database',
			array('start', 'A specific start date/time (e.g. 2015-01-01 00:00:00; January 1, 2015; -1 week)'),
			array('end', 'A specific end date/time (e.g. 2015-02-01 00:00:00; February 1, 2015; +40 day)'),
			array('osr', 'A specific OSR # to process'),
			array('sfdc-id', 'A specific Salesforce object id'),
			array('round', 'Starting round to start processing (e.g. 0 => first round)'),
			array('perround', '# of tickets to process per round (defaults to 100)'),
			
			'ticket salesforce import definition' => 'Import Salesforce OSR Definition',
		);
	}
}
?>
