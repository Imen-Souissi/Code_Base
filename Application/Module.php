<?php
namespace Application;

use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\Router\Http\Literal;
use Zend\Console\Adapter\AdapterInterface;
use Zend\ModuleManager\ModuleManager;
use Zend\ModuleManager\Feature\ServiceProviderInterface;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\ModuleManager\Feature\ConsoleBannerProviderInterface;
use Zend\ModuleManager\Feature\ConsoleUsageProviderInterface;
use Zend\EventManager\StaticEventManager;
use EdpModuleLayouts\Module as LayoutModule;

use Zend\View\Model\ViewModel;
use Zend\Log\Logger;

use Application\Model\Settings;

use Els\Mvc\Controller\SessionWriteRestfulInterface;

class Module extends LayoutModule implements ServiceProviderInterface, AutoloaderProviderInterface, ConfigProviderInterface, ConsoleBannerProviderInterface, ConsoleUsageProviderInterface {
	protected $ignore_init = false;
	protected $eventManager;
	
	public function init(ModuleManager $moduleManager) {
		$serviceLocator = $moduleManager->getEvent()->getParam('ServiceManager');
		$config = $serviceLocator->get('Application\Config');
		
		$eventManager = StaticEventManager::getInstance();
		$eventManager->attach('Zend\Mvc\Controller\AbstractActionController', 'dispatch', array($this, 'buildMenu'));
		// we need to fire this first before any dispatch to the actual restful controller
		$eventManager->attach('Zend\Mvc\Controller\AbstractRestfulController', 'dispatch', array($this, 'processSession'), 1000);
		$eventManager->attach('*', 'login-success', array($this, 'userLoggedIn'), 10);
		
		if($config['module_ignore_init'][__NAMESPACE__] === true) {
			$this->ignore_init = true;
		}
    }
	
    public function onBootstrap($e) {
		parent::onBootstrap($e);
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
	
	public function getServiceConfig() {
		return array(
			
		);
	}
	
	public function getConsoleBanner(AdapterInterface $console) {
		
	}
	
	public function getConsoleUsage(AdapterInterface $console) {
		return array(
			'documentation build [--date=] [--version=] [--cleanup] [--build-pdf]' => 'Build application documentation',
			array('date', 'The date to use (Default: current date)'),
			array('version', 'The version to use (Default: current version)'),
			array('cleanup', 'Cleanup temporary directories (Default: false)'),
			array('build-pdf', 'Include a PDF documentation build as well'),
			
			'table build' => 'Build application table',
			
			'notification email [--offset=] [--reemail]' => 'Email the notifications',
			array('offset', 'The cutoff time.  Will only process notifications after this offset (-1 hour)'),
			array('reemail', 'Include notifications that have been emailed but not seen yet')
		);
	}
	
	public function processSession($event) {
		if($event instanceof \Zend\Mvc\MvcEvent) {		
			$request = $event->getRequest();
		} else {
			$controller = $event->getTarget();
			$serviceLocator = $controller->getServiceLocator();
			$request = $serviceLocator->get('Request');
		}
		
		if($request instanceof \Zend\Console\Request) {
			// we don't need to do anything for console requests
			return;
		}
		
		$controller = $event->getTarget();		
		if($controller instanceof SessionWriteRestfulInterface) {
			return;
		}
		
		$serviceLocator = $controller->getServiceLocator();
		$sessionManager = $serviceLocator->get('session_manager');
		if($sessionManager) {
			// stop all write so that we can release the session lock
			$sessionManager->writeClose();
		}
	}
	
	public function buildMenu($event) {
		if($event instanceof \Zend\Mvc\MvcEvent) {		
			$request = $event->getRequest();
		} else {
			$controller = $event->getTarget();
			$serviceLocator = $controller->getServiceLocator();
			$request = $serviceLocator->get('Request');
		}
		
		if($request instanceof \Zend\Console\Request) {
			// we don't need to do anything for console requests
			return;
		}
		
		$controller = $event->getTarget();
		$serviceLocator = $controller->getServiceLocator();
		$router = $serviceLocator->get('Router');		
		$app_config = $serviceLocator->get('Application\Config');
		$config = $serviceLocator->get('Config');
		$eventManager = $controller->getEventManager();
		$logger = $serviceLocator->get('logger');
		
		$pluginManager = $serviceLocator->get('controllerPluginManager');
		$authorization = $pluginManager->get('authorization');
		
		$route_match = $router->match($request);
		$route_name = '';
		$controller_name = '';
		$action_name = '';
		
		if($route_match) {
			$route_name = $route_match->getMatchedRouteName();
			$controller_name = $route_match->getParam('controller');
			$action_name = $route_match->getParam('action');
		}
		
		$menus = $app_config['menus'];
		if(empty($menus)) {
			// no menu to process
			return;
		}
			
		$parentViewModel = $event->getViewModel();
		
		$menus = (is_array($menus)) ? $menus : array($menus);
		foreach($menus as $menuname => $options) {
			if(is_string($options)) {
				$menuname = $options;
				$options = array();
			}
			
			$menudef = $config[$menuname];
			if(empty($menudef)) {
				continue;
			}			
			
			$menudef = array_map(function($a) {
				if(is_string($a)) {
					return array(
						'template' => $a
					);
				} else {
					return $a;
				}
			}, $menudef);
			
			// sort the menu definitions
			uasort($menudef, function($a, $b) {
				if(is_array($a) && is_array($b)) {
					$a_order = ($a['order']) ? $a['order'] : 0;
					$b_order = ($b['order']) ? $b['order'] : 0;
					
					return ($a_order < $b_order) ? -1 : 1;
				} else {
					return 0;
				}
			});
			
			$menuViewModel = new ViewModel();
			$menuViewModel->setTemplate('menu/main');
			$menuViewModel->setVariables($controller->layout()->getVariables());
			
			// we are expecting a name=>template associative array
			foreach($menudef as $name => $template) {				
				$menu_options = $options;
				
				if(is_array($template)) {
					$menu_options = array_replace_recursive($options, ($template['options']) ? $template['options'] : array());
					$template = $template['template'];
				}
				
				if(!empty($menu_options['route']) && $route_name != $menu_options['route']) {
					// we don't need to load this menu
					continue;
				}
				if(!empty($menu_options['controller']) && $controller_name != $menu_options['controller']) {
					// we don't need to load this menu
					continue;
				}
				if(!empty($menu_options['action']) && $action_name != $menu_options['action']) {
					// we don't need to load this menu
					continue;
				}				
				
				if(!empty($menu_options['security'])) {
					if(!empty($menu_options['security']['controller']) && !empty($menu_options['security']['action'])) {
						if(!$authorization->isPermitted($menu_options['security']['controller'], $menu_options['security']['action'])) {
							// user does not have permission to load this menu
							continue;
						}
					}
				}
				
				$viewModel = new ViewModel();
				$viewModel->setTemplate($template);				
				$viewModel->setVariables($controller->layout()->getVariables());
				
				if($menu_options['load'] === true) {
					// we need to trigger the event for whoever is listening to provide use some data to display
					$return = $eventManager->trigger("{$menuname}-{$name}", $controller, array());
					
					foreach($return as $params) {
						if(is_array($params)) {
							$viewModel->setVariables($params);
						}
					}
				}
				
				$menuViewModel->addChild($viewModel, $name);
			}
			
			$parentViewModel->addChild($menuViewModel, $menuname);
		}
    }
	
	public function userLoggedIn($event) {
		if($event instanceof \Zend\Mvc\MvcEvent) {		
			$request = $event->getRequest();
		} else {
			$controller = $event->getTarget();
			$serviceLocator = $controller->getServiceLocator();
			$request = $serviceLocator->get('Request');
		}
		
		if($request instanceof \Zend\Console\Request) {
			// we don't need to do anything for console requests
			return;
		}
		
		$controller = $event->getTarget();
		$serviceLocator = $controller->getServiceLocator();		
		$logger = $serviceLocator->get('logger');
		$config = $serviceLocator->get('Config');
		
        $user_id = $event->getParam('user_id');
        $info = $event->getParam('info');
		
		$pluginManager = $serviceLocator->get('controllerPluginManager');
		$authorization = $pluginManager->get('authorization');
		
		if(!$this->ignore_init) {
			// check if this user has pinned a settings
			$settings_mdl = Settings::factory($serviceLocator);
			$setting = $settings_mdl->get(array(
				'user_id' => $user_id,
				'app' => $app_config['app'],
				'field' => 'landing'
			));
			
			if($setting !== false) {
				try {
					$route = \Zend\Json\Json::decode($setting->value, \Zend\Json\Json::TYPE_ARRAY);
					return array(
						'landing' => array(
							'route' => array(
								'route' => $route['route'],
								'params' => array(
									'controller' => $route['controller'],
									'action' => $route['action'],
									'id' => $route['id'],
								),
								'options' => array(
									'query' => $route['query'],
								),
							),
						),
					);
				} catch(\Exception $e) {
					// do  nothing
				}
			}
			
			if(!empty($config['auth']['landing'])) {
				$landing = $config['auth']['landing'];
				
				if(!empty($landing['route'])) {				
					if(!empty($landing['security'])) {
						if(!$authorization->isPermitted($landing['security']['controller'], $landing['security']['action'])) {
							return null;
						}
					}
					
					return array(
						'landing' => array(
							'route' => $landing['route'],
						),
					);
				}
			}
			
			return null;
		}
    }
}
?>