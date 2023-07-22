<?php
return array(
    'router' => array(
        'routes' => array(
            'power' => array(
                'type'    => 'Literal',
                'options' => array(
                    'route'    => '/power',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Power\Controller',
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
								'__NAMESPACE__' => 'Power\Controller\Export',
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
								'__NAMESPACE__' => 'Power\Controller\Rest',
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
	'console' => array(
		'router' => array(
			'routes' => array(
				'power-bna-import' => array(
					'options' => array(
						'route' => 'power power-iq import <type>',
						'defaults' => array(
							'controller' => 'Power\Controller\Script\PowerIq',
							'action' => 'import',
						),
					),
				),

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
			'Power\Controller\Script\PowerIq' => 'Power\Controller\Script\PowerIqController',
      'Power\Controller\Rest\PowerPdus' => 'Power\Controller\Rest\PowerPdusController',
        ),
    ),
	'module_layouts' => array(
		'Power' => 'layout/layout.phtml',
	),
	// define the config for the sidebar for this module
	'sidebar' => array(
	),
	'power-iq' => array(
		'host' => 'els-hq-poweriq1.englab.brocade.com',
		'username' => 'admin',
		'password' => 'raritan'
	),
);
?>
