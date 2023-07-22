<?php
return array(
    'router' => array(
        'routes' => array(
        	'brics' => array(
        		'type'    => 'Literal',
        		'options' => array(
        			'route'    => '/brics',
        			'defaults' => array(
        				'__NAMESPACE__' => 'Brics\Controller',
        				'controller'    => 'Index',
        				'action'        => 'index',
        			),
        		),
        		'may_terminate' => true,
        		'child_routes' => array(
        			'rest' => array(
        				'type' => 'Segment',
        				'options' => array(
        					'route' => '/rest/:controller/[:id]',
        					'constraints' => array(
        						'controller'	=> '[a-zA-Z][a-zA-Z0-9_-]*',
        						'id' 			=> '[a-zA-Z0-9]+',
        					),
							'defaults' => array(
								'__NAMESPACE__' => 'Brics\Controller\Rest',
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
        	'Brics\Controller\Index' => 'Brics\Controller\IndexController',
        	'Brics\Controller\Tutorial' => 'Brics\Controller\TutorialController',
        ),
    ),
	'module_layouts' => array(
		'Brics' => 'layout/layout.phtml',
	),
	// define the config for the sidebar for this module
	'sidebar' => array(
	),
    'topbar' => array(
    ),
);
?>
