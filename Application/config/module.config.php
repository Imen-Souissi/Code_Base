<?php
// TO CHANGE: not the best place to put the environment, we may end up pushing it to the Apache ENV
$env = file_get_contents(__DIR__ . '/../../../config/env');
$env = (empty($env)) ? \Els\View\Helper\Env::DEVELOPMENT : $env;

return array(
    'asset_manager' => array(
		'resolver_configs' => array(
			'paths' => array(
                'public',
			),
			'collections' => array(
				'd-assets/all.js' => array(
                    'assets/js/bootstrap.min.js',
                    'assets/js/jquery.raty.min.js',
                    'assets/js/jquery-ui.custom.min.js',
                    'assets/js/jquery.ui.touch-punch.min.js',
                    'assets/js/typeahead.jquery.min.js',
                    'assets/js/bloodhound.min.js',
                    'assets/js/jquery.validate.min.js',
                    'assets/js/chosen.jquery.min.js',
                    'assets/js/select2.min.js',
                    'assets/js/bootstrap-tag.min.js',
                    'assets/js/fuelux.spinner.min.js',
                    'assets/js/jquery-dateFormat.min.js',
                    'assets/js/jquery.number.min.js',
				),
				'd-assets/all.css' => array(
                    'assets/css/jquery-ui.custom.min.css',
                    'assets/css/chosen.min.css',
                    'assets/css/select2.min.css',
                    'assets/els/css/override.css',
				),
                'd-assets/select2-spinner.gif' => array(
                    'assets/css/select2-spinner.gif',
                ),
			),            
		),
        'caching' => array(
            'd-assets/all.js' => array(
                'cache' => ($env == 'development') ? '' : 'AssetManager\Cache\FilePathCache',
                'options' => array(
                    'dir' => 'public',
                ),
            ),
            'd-assets/all.css' => array(
                'cache' => ($env == 'development') ? '' : 'AssetManager\Cache\FilePathCache',
                'options' => array(
                    'dir' => 'public',
                ),
            ),
            'd-assets/select2-spinner.gif' => array(
                'cache' => ($env == 'development') ? '' : 'AssetManager\Cache\FilePathCache',
                'options' => array(
                    'dir' => 'public',
                ),
            ),
        ),
	),
    'router' => array(
        'routes' => array(
			'application' => array(
                'type'    => 'Literal',
                'options' => array(
                    'route'    => '/',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Application\Controller',
						'controller'    => 'Index',
                        'action'        => 'index',
                    ),
                ),
				'may_terminate' => true,
                'child_routes' => array(
                    'search' => array(
                        'type' => 'Literal',
                        'options' => array(
                            'route' => 'search',
                            'defaults' => array(
                                '__NAMESPACE__' => 'Application\Controller',
                                'controller'    => 'Index',
                                'action'        => 'search',
                            ),
                        ),
                        'may_terminate' => true,
                    ),
                    'application' => array(
                        'type' => 'Literal',
                        'options' => array(
                            'route'    => 'application',
                            'defaults' => array(
                                '__NAMESPACE__' => 'Application\Controller',
                                'controller'    => 'Index',
                                'action'        => 'index',
                            ),
                        ),
                        'may_terminate' => true,
                        'child_routes' => array(
                            'export' => array(
                                'type' => 'Segment',
                                'options' => array(
                                    'route' => '/export/:controller/[:id]',
                                    'constraints' => array(
                                        'controller'	=> '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'id' 			=> '[0-9\-]+',
                                    ),
                                    'defaults' => array(
                                        '__NAMESPACE__' => 'Application\Controller\Export',
                                        'action' => false,
                                    ),
                                ),
                            ),
                            'rest-tables' => array(
                                'type' => 'Segment',
                                'options' => array(
                                    'route' => '/rest/tables/[:table_id]/:controller/[:id]',
                                    'constraints' => array(
                                        'controller'	=> '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'table_id'      => '[0-9\-]+',
                                        'id' 			=> '[a-zA-Z0-9]+',
                                    ),
                                    'defaults' => array(
                                        '__NAMESPACE__' => 'Application\Controller\Rest\Tables',
                                        'action' => false,
                                    ),
                                ),
                            ),
                            'rest' => array(
                                'type' => 'Segment',
                                'options' => array(
                                    'route' => '/rest/:controller/[:id]',
                                    'constraints' => array(
                                        'controller'	=> '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'id' 			=> '[a-zA-Z0-9]+',
                                    ),
                                    'defaults' => array(
                                        '__NAMESPACE__' => 'Application\Controller\Rest',
                                        'action' => false,
                                    ),
                                ),
                            ),
                            'default' => array(
                                'type'	=> 'Segment',
                                'options' => array(
                                    'route'	=> '/[:controller[/][:action[/:id]]]',
                                    'constraints' => array(
                                        'controller'	=> '(?!rest|export)[a-zA-Z][a-zA-Z0-9_-]*',
                                        'action'     	=> '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'id' 			=> '[0-9]*',
                                    ),
                                    'defaults' => array(
                                        'action' => 'index',
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),
            ),            
        ),
    ),
    'console' => array(
		'router' => array(
			'routes' => array(
				'application-documentation' => array(
					'options' => array(
						'route' => 'documentation build [--date=] [--version=] [--cleanup] [--build-pdf]',
						'defaults' => array(
							'controller' => 'Application\Controller\Script\Documentation',
							'action' => 'build',
						),
					),
				),
                'application-table' => array(
					'options' => array(
						'route' => 'table build',
						'defaults' => array(
							'controller' => 'Application\Controller\Script\Table',
							'action' => 'build',
						),
					),
				),
                'application-notification-email' => array(
                    'options' => array(
						'route' => 'notification email [--offset=] [--reemail]',
						'defaults' => array(
							'controller' => 'Application\Controller\Script\Notification',
                            'action' => 'email'
						),
					),
                ),
			),
		),
	),
    'service_manager' => array(
        'abstract_factories' => array(
            // http://framework.zend.com/manual/2.2/en/modules/zend.mvc.services.html#zend-cache-service-storagecacheabstractservicefactory
            'Zend\Cache\Service\StorageCacheAbstractServiceFactory',
            // http://framework.zend.com/manual/2.2/en/modules/zend.mvc.services.html#zend-log-loggerabstractservicefactory
            'Zend\Log\LoggerAbstractServiceFactory',
        ),
        'aliases' => array(
            'translator' => 'MvcTranslator',
        ),
    ),
    'view_manager' => array(
		'strategies' => array(
			'ViewJsonStrategy',
		),
		'display_not_found_reason' => true,
        'display_exceptions'       => true,
        'doctype'                  => 'HTML5',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'template_map' => array(
            'layout/layout'           => __DIR__ . '/../view/layout/layout.phtml',			
            'error/404'               => __DIR__ . '/../view/error/404.phtml',
            'error/index'             => __DIR__ . '/../view/error/index.phtml',
        ),
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
    ),
	'view_helpers' => array(
		'invokables' => array(
			'wizard' => 'Els\View\Helper\Wizard',
			'formatter' => 'Els\View\Helper\Formatter',
		),
        'factories' => array(
            'route' => 'Els\View\Helper\RouteFactory',
			'session' => 'Els\View\Helper\SessionFactory',
            'notification' => 'Application\View\Helper\NotificationFactory',
        ),
    ),
    'controllers' => array(
        'invokables' => array(
            'Application\Controller\Index' => 'Application\Controller\IndexController',
            'Application\Controller\Markdown' => 'Application\Controller\MarkdownController',
            'Application\Controller\Tables' => 'Application\Controller\TablesController',
            'Application\Controller\Notifications' => 'Application\Controller\NotificationsController',
            'Application\Controller\MaskStrings' => 'Application\Controller\MaskStringsController',
            
            'Application\Controller\Script\Documentation' => 'Application\Controller\Script\DocumentationController',
            'Application\Controller\Script\Table' => 'Application\Controller\Script\TableController',
            'Application\Controller\Script\Notification' => 'Application\Controller\Script\NotificationController',
            
            'Application\Controller\Rest\Countries' => 'Application\Controller\Rest\CountriesController',
            'Application\Controller\Rest\Cities' => 'Application\Controller\Rest\CitiesController',
            'Application\Controller\Rest\States' => 'Application\Controller\Rest\StatesController',
            
            'Application\Controller\Rest\Tables\UserColumns' => 'Application\Controller\Rest\Tables\UserColumnsController',
        ),
    ),
	'controller_plugins' => array(
		'invokables' => array(
			'formatter' => 'Els\Controller\Plugin\Formatter',
            'pinfilter' => 'Application\Controller\Plugin\PinFilter',
		),
		'factories' => array(
			'routeinfo' => 'Els\Controller\Plugin\Route\InfoFactory',
			'wizard' => 'Els\Controller\Plugin\WizardFactory',
		),
    ),    
	'wizard' => array(
		'wizard_field' => 'wizard'
	),
    'documentation' => array(
        // documentation file configurations
    ),
    'table' => array(
        // table file configurations
    ),
    // notification related configuration
	'notification' => array(
		'max' => 5
	),
    'presidebar' => array(
		'search' => 'menu/presidebar/search',
	),
    // define the config for the topbar for this module
	'topbar' => array(
        'search' => array(
            'template' => 'menu/topbar/search',
            'order' => 0,
        ),
		'notifications' => array(
            'template' => 'menu/topbar/notifications',
            'order' => 10,
        ),
	),
);