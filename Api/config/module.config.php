<?php
return array(	
    'router' => array(
        'routes' => array(
            'api' => array(
                'type'    => 'Literal',
                'options' => array(
                    'route'    => '/api',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Api\Controller',
						'controller'    => 'Index',
                        'action'        => 'index',
                    ),
                ),
				'may_terminate' => true,
				'child_routes' => array(
                    'services' => array(
                        'type' => 'Segment',
                        'options' => array(
                            'route' => '/v[:version/[:apikey/[:service/[:method]]]]',
                            'constraints' => array(
                                'version' => '[0-9]+',
                                'apikey' => '[a-zA-Z0-9]*',
                                'service' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'method' => '[a-zA-Z][a-zA-Z0-9_-]*',                                
                            ),
                            'defaults' => array(
                                'controller' => 'Api',
                            ),
                        ),
                    ),
                    'rest' => array(
						'type' => 'Segment',
						'options' => array(
							'route' => '/rest/:controller/[:id]',
							'constraints' => array(
								'controller'	=> '[a-zA-Z][a-zA-Z0-9_-]*',
								'id' 			=> '[0-9\-]+',
							),
							'defaults' => array(
								'__NAMESPACE__' => 'Api\Controller\Rest',
								'action' => false,
							),
						),
					),
                    'rest-key' => array(
						'type' => 'Segment',
						'options' => array(
							'route' => '/rest/keys/[:key_id]/:controller/[:id]',
							'constraints' => array(
								'controller'	=> '[a-zA-Z][a-zA-Z0-9_-]*',
                                'key_id' 	    => '[0-9\-]+',
								'id' 			=> '[0-9\-]+',
							),
							'defaults' => array(
								'__NAMESPACE__' => 'Api\Controller\Rest\Keys',
								'action' => false,
							),
						),
					),
                    'rest-service' => array(
						'type' => 'Segment',
						'options' => array(
							'route' => '/rest/services/[:service_id]/:controller/[:id]',
							'constraints' => array(
								'controller'	=> '[a-zA-Z][a-zA-Z0-9_-]*',
                                'service_id' 	=> '[0-9\-]+',
								'id' 			=> '[0-9\-]+',
							),
							'defaults' => array(
								'__NAMESPACE__' => 'Api\Controller\Rest\Services',
								'action' => false,
							),
						),
					),
					'default' => array(
						'type'	=> 'Segment',
						'options' => array(
							'route'	=> '/[:controller/[:action[/:id]]]',
							'constraints' => array(
								'controller'	=> '(?!rest)[a-zA-Z][a-zA-Z0-9_-]*',
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
	'console' => array(
		'router' => array(
			'routes' => array(
			),
		),
	),
    'view_manager' => array(
        'template_map' => array(
        ),
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
    ),
    'controllers' => array(
        'invokables' => array(
            'Api\Controller\Api' => 'Api\Controller\ApiController',
            'Api\Controller\Index' => 'Api\Controller\IndexController',
            'Api\Controller\Keys' => 'Api\Controller\KeysController',
            'Api\Controller\Services' => 'Api\Controller\ServicesController',
            
            'Api\Controller\Rest\Keys' => 'Api\Controller\Rest\KeysController',
            'Api\Controller\Rest\Services' => 'Api\Controller\Rest\ServicesController',
            
            'Api\Controller\Rest\Services\Methods' => 'Api\Controller\Rest\Services\MethodsController',
            
            'Api\Controller\Rest\Keys\AvailableServiceMethods' => 'Api\Controller\Rest\Keys\AvailableServiceMethodsController',
            'Api\Controller\Rest\Keys\AutoServiceMethods' => 'Api\Controller\Rest\Keys\AutoServiceMethodsController',
            'Api\Controller\Rest\Keys\ServiceMethods' => 'Api\Controller\Rest\Keys\ServiceMethodsController',
        ),
    ),
    'log' => array(
        'api_logger' => array(
            'writers' => array(
                array(
                    'name' => 'stream',
                    'priority' => 1,
                    'options' => array(
                        'stream' => 'data/logs/api.log',
                    ),
                    'filters' => array(
                        array(
                            'name' => 'priority',
                            'options' => array(
                                'priority' => E_ALL & ~E_STRICT & ~E_WARNING & ~E_NOTICE,
                            )
                        ),
                    ),
                ),
            ),
        ),
    ),
	'module_layouts' => array(
		'Api' => 'layout/layout.phtml',
	),
    // define the config for the sidebar for this module
	'sidebar' => array(
		'api' => array(
            'template' => 'menu/sidebar/api',
            'order' => 2
        ),
    ),
    'api' => array(
        // default hostname to use for displaying in documentation
        'hostname' => 'localhost',
        // main app identifier, which will be use in validating duplicate services
        'app' => 'api',
        // security resource identifier app, which will be use in the url generation for pulling security resources
        'security_resource_app' => 'api',
        // all registered services
        'services' => array(
            
        ),
        'session_field' => 'api',
    ),
    'documentation' => array(
        __DIR__ . '/documentation.config.php'
    ),
);
?>