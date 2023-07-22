<?php
return array(
    'router' => array(
        'routes' => array(
            'ticket' => array(
                'type'    => 'Literal',
                'options' => array(
                    'route'    => '/ticket',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Ticket\Controller',
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
								'__NAMESPACE__' => 'Ticket\Controller\Rest',
								'action' => false,
							),
						),
					),
                    'rest-systems' => array(
						'type' => 'Segment',
						'options' => array(
							'route' => '/rest/systems/[:system_id]/:controller/[:id]',
							'constraints' => array(
								'controller'	=> '[a-zA-Z][a-zA-Z0-9_\-]*',
								'system_id'     => '[0-9\-]+',
								'id'			=> '[^\/]+'
							),
							'defaults' => array(
								'__NAMESPACE__' => 'Ticket\Controller\Rest\Systems',
								'action' => false,
							),
						),
					),
          'export' => array(
            'type' => 'Segment',
            'options' => array(
              'route' => '/export/:controller/[:id]',
              'constraints' => array(
                'controller'	=> '[a-zA-Z][a-zA-Z0-9_-]*',
                'id' 			=> '[a-zA-Z0-9]+',
              ),
              'defaults' => array(
                '__NAMESPACE__' => 'Ticket\Controller\Export',
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
				'ticket-import-footprints' => array(
					'options' => array(
						'route' => 'ticket footprints import [--start=] [--end=] [--ticket=] [--round=] [--perround=]',
						'defaults' => array(
							'controller' => 'Ticket\Controller\Script\Footprints',
							'action' => 'import',
						),
					),
				),
                'ticket-import-salesforce' => array(
					'options' => array(
						'route' => 'ticket salesforce import [--start=] [--end=] [--osr=] [--sfdc-id=] [--round=] [--perround=]',
						'defaults' => array(
							'controller' => 'Ticket\Controller\Script\Salesforce',
							'action' => 'import',
						),
					),
				),
                'ticket-import-salesforce-definition' => array(
					'options' => array(
						'route' => 'ticket salesforce import definition',
						'defaults' => array(
							'controller' => 'Ticket\Controller\Script\Salesforce',
							'action' => 'import-definition',
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
			'Ticket\Controller\Script\Footprints' => 'Ticket\Controller\Script\FootprintsController',
			'Ticket\Controller\Script\Salesforce' => 'Ticket\Controller\Script\SalesforceController',

			'Ticket\Controller\Tickets' => 'Ticket\Controller\TicketsController',
            'Ticket\Controller\ServiceRequests' => 'Ticket\Controller\ServiceRequestsController',

            'Ticket\Controller\Rest\Tickets' => 'Ticket\Controller\Rest\TicketsController',
			'Ticket\Controller\Rest\SubTickets' => 'Ticket\Controller\Rest\SubTicketsController',
            'Ticket\Controller\Rest\Systems' => 'Ticket\Controller\Rest\SystemsController',
            'Ticket\Controller\Rest\Systems\ServiceRequests' => 'Ticket\Controller\Rest\Systems\ServiceRequestsController',
            'Ticket\Controller\Rest\Systems\Statuses' => 'Ticket\Controller\Rest\Systems\StatusesController',
            'Ticket\Controller\Rest\Systems\Products' => 'Ticket\Controller\Rest\Systems\ProductsController',
            'Ticket\Controller\Rest\Systems\LabLocations' => 'Ticket\Controller\Rest\Systems\LabLocationsController',
            'Ticket\Controller\Rest\Systems\OsrTypes' => 'Ticket\Controller\Rest\Systems\OsrTypesController',
        ),
    ),
	'module_layouts' => array(
		'Ticket' => 'layout/layout.phtml',
	),
    // define the config for the sidebar for this module
	'sidebar' => array(
		'ticket' => array(
            'template' => 'menu/sidebar/ticket',
            'order' => 4.2,
        ),
	),
    'table' => array(
        __DIR__ . '/table.config.php'
    ),
);
?>
