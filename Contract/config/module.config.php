<?php
return array(
    'router' => array(
        'routes' => array(
        	'contract' => array(
        		'type'    => 'Literal',
        		'options' => array(
        			'route'    => '/contract',
        			'defaults' => array(
        				'__NAMESPACE__' => 'Contract\Controller',
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
								'__NAMESPACE__' => 'Contract\Controller\Rest',
								'action' => false,
							),
						),
					),
        			'rest-contracts' => array(
        				'type' => 'Segment',
        				'options' => array(
        					'route' => '/rest/contracts/[:contract_id]/:controller/[:id]',
        					'constraints' => array(
        						'controller'	=> '[a-zA-Z][a-zA-Z0-9_\-]*',
        						'contract_id'   => '[0-9\-]+',
        						'id'			=> '[^\/]+'
        					),
        					'defaults' => array(
        						'__NAMESPACE__' => 'Contract\Controller\Rest\Contracts',
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
        						'__NAMESPACE__' => 'Contract\Controller\Export',
        						'action' => false,
        					),
        				),
        			),
        			'default' => array(
        				'type'	=> 'Segment',
        				'options' => array(
        					'route'	=> '/[:controller/[:action[/:id]]]',
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
        // first cron
				'calendar-quarters-add' => array(
					'options' => array(
						'route' => 'contract calendar-quarters-add',
						'defaults' => array(
							'controller' => 'Contract\Controller\Script',
							'action' => 'calendar-quarters-add',
						),
					),
				),
        // Second cron
				'payment-notification' => array(
					'options' => array(
						'route' => 'contract payment-notification [--dry-run] [--to=]',
						'defaults' => array(
							'controller' => 'Contract\Controller\Script',
							'action' => 'payment-notification',
						),
					),
				),
        // third cron
        'expiration-notification' => array(
          'options' => array(
            'route' => 'contract expiration-notification [--dry-run] [--to=]',
            'defaults' => array(
              'controller' => 'Contract\Controller\Script',
              'action' => 'expiration-notification',
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
        	'Contract\Controller\Contracts' => 'Contract\Controller\ContractsController',
        	'Contract\Controller\ContractDocuments' => 'Contract\Controller\ContractDocumentsController',
        	'Contract\Controller\NotificationSchedules' => 'Contract\Controller\NotificationSchedulesController',
        	'Contract\Controller\PaymentSchedules' => 'Contract\Controller\PaymentSchedulesController',
        	'Contract\Controller\Script' => 'Contract\Controller\ScriptController',
        	'Contract\Controller\Statuses' => 'Contract\Controller\StatusesController',
        	'Contract\Controller\Types' => 'Contract\Controller\TypesController',
        	'Contract\Controller\Vendors' => 'Contract\Controller\VendorsController',

        	'Contract\Controller\Export\Contracts' => 'Contract\Controller\Export\ContractsController',
        	'Contract\Controller\Export\Payments' => 'Contract\Controller\Export\PaymentsController',
        	'Contract\Controller\Export\PaymentByQuarter' => 'Contract\Controller\Export\PaymentByQuarterController',
        	'Contract\Controller\Export\PaymentRunRatesRollup' => 'Contract\Controller\Export\PaymentRunRatesRollupController',

        	'Contract\Controller\Rest\CalendarQuarters' => 'Contract\Controller\Rest\CalendarQuartersController',
        	'Contract\Controller\Rest\Contracts' => 'Contract\Controller\Rest\ContractsController',
        	'Contract\Controller\Rest\ContractContactLinks' => 'Contract\Controller\Rest\ContractContactLinksController',
        	'Contract\Controller\Rest\ContractDevices' => 'Contract\Controller\Rest\ContractDevicesController',
        	'Contract\Controller\Rest\ContractDocuments' => 'Contract\Controller\Rest\ContractDocumentsController',
        	'Contract\Controller\Rest\ContractLookup' => 'Contract\Controller\Rest\ContractLookupController',
        	'Contract\Controller\Rest\ContractStatuses' => 'Contract\Controller\Rest\ContractStatusesController',
        	'Contract\Controller\Rest\ContractTypes' => 'Contract\Controller\Rest\ContractTypesController',
        	'Contract\Controller\Rest\ContractUserLinks' => 'Contract\Controller\Rest\ContractUserLinksController',
        	'Contract\Controller\Rest\NotificationSchedules' => 'Contract\Controller\Rest\NotificationSchedulesController',
        	'Contract\Controller\Rest\Payments' => 'Contract\Controller\Rest\PaymentsController',
        	'Contract\Controller\Rest\PaymentByQuarter' => 'Contract\Controller\Rest\PaymentByQuarterController',
        	'Contract\Controller\Rest\PaymentRunRates' => 'Contract\Controller\Rest\PaymentRunRatesController',
        	'Contract\Controller\Rest\PaymentRunRatesRollup' => 'Contract\Controller\Rest\PaymentRunRatesRollupController',
        	'Contract\Controller\Rest\PaymentSchedules' => 'Contract\Controller\Rest\PaymentSchedulesController',
        	'Contract\Controller\Rest\Vendors' => 'Contract\Controller\Rest\VendorsController',
        	'Contract\Controller\Rest\VendorContacts' => 'Contract\Controller\Rest\VendorContactsController',

        	'Contract\Controller\Rest\Contracts\Labviews' => 'Contract\Controller\Rest\Contracts\LabviewsController',
        	'Contract\Controller\Rest\Contracts\AvailableLabviews' => 'Contract\Controller\Rest\Contracts\AvailableLabviewsController',
        ),
    ),
	'module_layouts' => array(
		'Contract' => 'layout/layout.phtml',
	),
	// define the config for the sidebar for this module
	'sidebar' => array(
		'contract' => array(
            'template' => 'menu/sidebar/contract',
            'order' => 13,
        ),
	),
    'topbar' => array(
    ),
	'contract' => array(
		'docdir' => 'public/docs/contracts',
	),
);
?>
