<?php
// TO CHANGE: not the best place to put the environment, we may end up pushing it to the Apache ENV
$env = file_get_contents(__DIR__ . '/../../../config/env');
$env = (empty($env)) ? \Els\View\Helper\Env::DEVELOPMENT : $env;

return array(
    'asset_manager' => array(
		'resolver_configs' => array(
			'paths' => array(
				__DIR__ . '/../public',
			),
			'collections' => array(
				'd-assets/gem/js/site-layout-editor.js' => array(
					'd-assets/site-layout/js/editor.js',
                    'd-assets/site-layout/js/object-list.js',
                    'd-assets/site-layout/js/upload-modal.js',
                    'd-assets/site-layout/js/resize-modal.js',
                    'd-assets/site-layout/js/rotate-modal.js',
                    'd-assets/site-layout/js/renderer.js',
                    'd-assets/site-layout/js/snap-plugins.js',
				),
                'd-assets/gem/js/site-layout-viewer.js' => array(
					'd-assets/site-layout/js/viewer.js',
                    'd-assets/site-layout/js/free-form-viewer.js',
                    'd-assets/site-layout/js/renderer.js',
                    'd-assets/site-layout/js/snap-plugins.js',
				),
				'd-assets/gem/css/site-layout-editor.css' => array(
					'd-assets/site-layout/css/editor.css',
                    'd-assets/site-layout/css/object-list.css',
                    'd-assets/site-layout/css/upload-modal.css',
                    'd-assets/site-layout/css/resize-modal.css',
                    'd-assets/site-layout/css/rotate-modal.css',
				),
                'd-assets/gem/css/site-layout-viewer.css' => array(
					'd-assets/site-layout/css/viewer.css',
				),
			),
		),
        'caching' => array(
            'd-assets/gem/js/site-layout-editor.js' => array(
                'cache' => ($env == 'development') ? '' : 'AssetManager\Cache\FilePathCache',
                'options' => array(
                    'dir' => 'public',
                ),
            ),
            'd-assets/gem/js/site-layout-viewer.js' => array(
                'cache' => ($env == 'development') ? '' : 'AssetManager\Cache\FilePathCache',
                'options' => array(
                    'dir' => 'public',
                ),
            ),
            'd-assets/gem/css/site-layout-editor.css' => array(
                'cache' => ($env == 'development') ? '' : 'AssetManager\Cache\FilePathCache',
                'options' => array(
                    'dir' => 'public',
                ),
            ),
            'd-assets/gem/css/site-layout-viewer.css' => array(
                'cache' => ($env == 'development') ? '' : 'AssetManager\Cache\FilePathCache',
                'options' => array(
                    'dir' => 'public',
                ),
            ),
        ),
	),
    'router' => array(
        'routes' => array(
            'gem' => array(
                'type'    => 'Literal',
                'options' => array(
                    'route'    => '/gem',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Gem\Controller',
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
								'__NAMESPACE__' => 'Gem\Controller\Export',
								'action' => false,
							),
						),
					),
					'export-testbeds' => array(
						'type' => 'Segment',
						'options' => array(
							'route' => '/export/testbeds/[:testbed_id]/:controller/[:id]',
							'constraints' => array(
								'controller'	=> '[a-zA-Z][a-zA-Z0-9_\-]*',
								'testbed_id' 	=> '[0-9\-]+',
								'id'			=> '[^\/]+'
							),
							'defaults' => array(
								'__NAMESPACE__' => 'Gem\Controller\Export\Testbeds',
								'action' => false,
							),
						),
					),
                    'export-assets' => array(
						'type' => 'Segment',
						'options' => array(
							'route' => '/export/assets/[:asset_id]/:controller/[:id]',
							'constraints' => array(
								'controller'	=> '[a-zA-Z][a-zA-Z0-9_\-]*',
								'asset_id' 	    => '[0-9\-]+',
								'id'			=> '[^\/]+'
							),
							'defaults' => array(
								'__NAMESPACE__' => 'Gem\Controller\Export\Assets',
								'action' => false,
							),
						),
					),
                    'export-sites' => array(
						'type' => 'Segment',
						'options' => array(
							'route' => '/export/sites/[:site_id]/:controller/[:id]',
							'constraints' => array(
								'controller'	=> '[a-zA-Z][a-zA-Z0-9_\-]*',
								'site_id'       => '[0-9\-]+',
								'id'			=> '[^\/]+'
							),
							'defaults' => array(
								'__NAMESPACE__' => 'Gem\Controller\Export\Sites',
								'action' => false,
							),
						),
					),
                    'export-labs' => array(
						'type' => 'Segment',
						'options' => array(
							'route' => '/export/labs/[:lab_id]/:controller/[:id]',
							'constraints' => array(
								'controller'	=> '[a-zA-Z][a-zA-Z0-9_\-]*',
								'lab_id'        => '[0-9\-]+',
								'id'			=> '[^\/]+'
							),
							'defaults' => array(
								'__NAMESPACE__' => 'Gem\Controller\Export\Labs',
								'action' => false,
							),
						),
					),
                    'export-racks' => array(
						'type' => 'Segment',
						'options' => array(
							'route' => '/export/racks/[:rack_id]/:controller/[:id]',
							'constraints' => array(
								'controller'	=> '[a-zA-Z][a-zA-Z0-9_\-]*',
								'rack_id'       => '[0-9\-]+',
								'id'			=> '[^\/]+'
							),
							'defaults' => array(
								'__NAMESPACE__' => 'Gem\Controller\Export\Racks',
								'action' => false,
							),
						),
					),
                    'export-devices' => array(
						'type' => 'Segment',
						'options' => array(
							'route' => '/export/devices/[:device_id]/:controller/[:id]',
							'constraints' => array(
								'controller'	=> '[a-zA-Z][a-zA-Z0-9_\-]*',
								'device_id'     => '[0-9\-]+',
								'id'			=> '[^\/]+'
							),
							'defaults' => array(
								'__NAMESPACE__' => 'Gem\Controller\Export\Devices',
								'action' => false,
							),
						),
					),
                    'export-virtuals' => array(
						'type' => 'Segment',
						'options' => array(
							'route' => '/export/virtuals/[:virtual_id]/:controller/[:id]',
							'constraints' => array(
								'controller'	=> '[a-zA-Z][a-zA-Z0-9_\-]*',
								'virtual_id'     => '[0-9\-]+',
								'id'			=> '[^\/]+'
							),
							'defaults' => array(
								'__NAMESPACE__' => 'Gem\Controller\Export\Virtuals',
								'action' => false,
							),
						),
					),
                    'export-labviews' => array(
						'type' => 'Segment',
						'options' => array(
							'route' => '/export/labviews/[:labview_id]/:controller/[:id]',
							'constraints' => array(
								'controller'	=> '[a-zA-Z][a-zA-Z0-9_\-]*',
								'labview_id'    => '[0-9\-]+',
								'id'			=> '[^\/]+'
							),
							'defaults' => array(
								'__NAMESPACE__' => 'Gem\Controller\Export\Labviews',
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
								'id' 			=> '[0-9\-]+',
							),
							'defaults' => array(
								'__NAMESPACE__' => 'Gem\Controller\Rest',
								'action' => false,
							),
						),
					),
					'rest-testbeds' => array(
						'type' => 'Segment',
						'options' => array(
							'route' => '/rest/testbeds/[:testbed_id]/:controller/[:id]',
							'constraints' => array(
								'controller'	=> '[a-zA-Z][a-zA-Z0-9_\-]*',
								'testbed_id' 	=> '[0-9\-]+',
								'id'			=> '[^\/]+'
							),
							'defaults' => array(
								'__NAMESPACE__' => 'Gem\Controller\Rest\Testbeds',
								'action' => false,
							),
						),
					),
                    'rest-sites' => array(
						'type' => 'Segment',
						'options' => array(
							'route' => '/rest/sites/[:site_id]/:controller/[:id]',
							'constraints' => array(
								'controller'	=> '[a-zA-Z][a-zA-Z0-9_\-]*',
								'site_id' 	    => '[0-9\-]+',
								'id'			=> '[^\/]+'
							),
							'defaults' => array(
								'__NAMESPACE__' => 'Gem\Controller\Rest\Sites',
								'action' => false,
							),
						),
					),
                    'rest-labs' => array(
						'type' => 'Segment',
						'options' => array(
							'route' => '/rest/labs/[:lab_id]/:controller/[:id]',
							'constraints' => array(
								'controller'	=> '[a-zA-Z][a-zA-Z0-9_\-]*',
								'lab_id' 	    => '[0-9\-]+',
								'id'			=> '[^\/]+'
							),
							'defaults' => array(
								'__NAMESPACE__' => 'Gem\Controller\Rest\Labs',
								'action' => false,
							),
						),
					),
                    'rest-racks' => array(
						'type' => 'Segment',
						'options' => array(
							'route' => '/rest/racks/[:rack_id]/:controller/[:id]',
							'constraints' => array(
								'controller'	=> '[a-zA-Z][a-zA-Z0-9_\-]*',
								'rack_id' 	    => '[0-9\-]+',
								'id'			=> '[^\/]+'
							),
							'defaults' => array(
								'__NAMESPACE__' => 'Gem\Controller\Rest\Racks',
								'action' => false,
							),
						),
					),
					       'rest-devices' => array(
						'type' => 'Segment',
						'options' => array(
							'route' => '/rest/devices/[:device_id]/:controller/[:id]',
							'constraints' => array(
								'controller'	=> '[a-zA-Z][a-zA-Z0-9_\-]*',
								'device_id' 	=> '[0-9\-]+',
								'id'			=> '[^\/]+'
							),
							'defaults' => array(
								'__NAMESPACE__' => 'Gem\Controller\Rest\Devices',
								'action' => false,
							),
						),
					),
                    'rest-virtuals' => array(
						'type' => 'Segment',
						'options' => array(
							'route' => '/rest/virtuals/[:virtual_id]/:controller/[:id]',
							'constraints' => array(
								'controller'	=> '[a-zA-Z][a-zA-Z0-9_\-]*',
								'virtual_id' 	=> '[0-9\-]+',
								'id'			=> '[^\/]+'
							),
							'defaults' => array(
								'__NAMESPACE__' => 'Gem\Controller\Rest\Virtuals',
								'action' => false,
							),
						),
					),
                    'rest-assets' => array(
						'type' => 'Segment',
						'options' => array(
							'route' => '/rest/assets/[:asset_id]/:controller/[:id]',
							'constraints' => array(
								'controller'	=> '[a-zA-Z][a-zA-Z0-9_\-]*',
								'asset_id' 	    => '[0-9\-]+',
								'id'			=> '[^\/]+'
							),
							'defaults' => array(
								'__NAMESPACE__' => 'Gem\Controller\Rest\Assets',
								'action' => false,
							),
						),
					),
                    'rest-assets-layouts' => array(
						'type' => 'Segment',
						'options' => array(
							'route' => '/rest/assets/[:asset_id]/layouts/[:layout_id]/:controller/[:id]',
							'constraints' => array(
								'controller'	=> '[a-zA-Z][a-zA-Z0-9_\-]*',
								'asset_id' 	    => '[0-9\-]+',
                                'layout_id'     => '[0-9\-]+',
								'id'			=> '[^\/]+'
							),
							'defaults' => array(
								'__NAMESPACE__' => 'Gem\Controller\Rest\Assets\Layouts',
								'action' => false,
							),
						),
					),
                    'rest-data-point-types' => array(
						'type' => 'Segment',
						'options' => array(
							'route' => '/rest/data-point-types/[:type_id]/:controller/[:id]',
							'constraints' => array(
								'controller'	=> '[a-zA-Z][a-zA-Z0-9_\-]*',
								'type_id' 	    => '[0-9\-]+',
								'id'			=> '[^\/]+'
							),
							'defaults' => array(
								'__NAMESPACE__' => 'Gem\Controller\Rest\DataPointTypes',
								'action' => false,
							),
						),
					),
                    'rest-labviews' => array(
						'type' => 'Segment',
						'options' => array(
							'route' => '/rest/labviews/[:labview_id]/:controller/[:id]',
							'constraints' => array(
								'controller'	=> '[a-zA-Z][a-zA-Z0-9_\-]*',
								'labview_id'    => '[0-9\-]+',
								'id'			=> '[^\/]+'
							),
							'defaults' => array(
								'__NAMESPACE__' => 'Gem\Controller\Rest\Labviews',
								'action' => false,
							),
						),
					),
					'default' => array(
						'type'	=> 'Segment',
						'options' => array(
							'route'	=> '/:controller/[:action[/:id]]',
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
				'gem-tgen-import' => array(
					'options' => array(
						'route' => 'gem tgen import <type>',
						'defaults' => array(
							'controller' => 'Gem\Controller\Script\Tgen',
							'action' => 'import',
						),
					),
				),
				'gem-scan-import' => array(
					'options' => array(
						'route' => 'gem scan import <type>',
						'defaults' => array(
							'controller' => 'Gem\Controller\Script\Scan',
							'action' => 'import',
						),
					)
				),
				'gem-add-unknown' => array(
					'options' => array(
						'route' => 'gem add-unknown',
						'defaults' => array(
							'controller' => 'Gem\Controller\Script\Imdb',
							'action' => 'add-unknown'
						),
					),
				),
				'gem-sphinx-reindex' => array(
					'options' => array(
						'route' => 'gem sphinx reindex [--offset=] [--identifier=] [<type>]',
						'defaults' => array(
							'controller' => 'Gem\Controller\Script\Sphinx',
							'action' => 'reindex',
						),
					),
				),
                'gem-sphinx-cleanup' => array(
					'options' => array(
						'route' => 'gem sphinx cleanup [--offset=] [--identifier=] [<type>]',
						'defaults' => array(
							'controller' => 'Gem\Controller\Script\Sphinx',
							'action' => 'cleanup',
						),
					),
				),
                'gem-sphinx-cleanup-ghost' => array(
					'options' => array(
						'route' => 'gem sphinx cleanup ghost [--offset=] [--identifier=] [--id=] [<type>]',
						'defaults' => array(
							'controller' => 'Gem\Controller\Script\Sphinx',
							'action' => 'cleanupGhost',
						),
					),
				),
                'gem-sphinx-validate' => array(
					'options' => array(
						'route' => 'gem sphinx validate [--offset=] [--identifier=] [--id=] [<type>]',
						'defaults' => array(
							'controller' => 'Gem\Controller\Script\Sphinx',
							'action' => 'validate',
						),
					),
				),
				'gem-cleanup-reservation' => array(
					'options' => array(
						'route' => 'gem cleanup reservation [--delay=] [--expirethreshold=]',
						'defaults' => array(
							'controller' => 'Gem\Controller\Script\Reservations',
							'action' => 'cleanup-reservation',
						),
					),
				),
				'gem-toss-import' => array(
					'options' => array(
						'route' => 'gem toss import [--ticket=]',
						'defaults' => array(
							'controller' => 'Gem\Controller\Script\Toss',
							'action' => 'import',
						),
					),
				),
				'gem-toss-cleanup-storage' => array(
					'options' => array(
						'route' => 'gem toss cleanup storage [--delay=] [--expirethreshold=]',
						'defaults' => array(
							'controller' => 'Gem\Controller\Script\Toss',
							'action' => 'cleanup',
						),
					),
				),
				'gem-stats-aggregate' => array(
					'options' => array(
						'route' => 'gem stats aggregate <type>',
						'defaults' => array(
							'controller' => 'Gem\Controller\Script\Stats',
							'action' => 'aggregate',
						),
					),
				),
                'gem-data-points-build' => array(
					'options' => array(
						'route' => 'gem data-points build <type>',
						'defaults' => array(
							'controller' => 'Gem\Controller\Script\DataPoints',
							'action' => 'build',
						),
					),
				),
                'gem-utilization-build' => array(
                    'options' => array(
						'route' => 'gem utilization build [--date=]',
						'defaults' => array(
							'controller' => 'Gem\Controller\Script\Utilizations',
							'action' => 'build',
						),
					),
                ),
				'gem-hr-changes-rack-contacts' => array(
					'options' => array(
						'route' => 'gem hr-changes rack-contacts --to= [--date=] [--interval=]',
						'defaults' => array(
							'controller' => 'Gem\Controller\Script\HrChanges',
							'action' => 'rack-contacts',
						),
					),
				),
                'gem-device-dates-compute' => array(
					'options' => array(
						'route' => 'gem device dates compute [--offset=]',
						'defaults' => array(
							'controller' => 'Gem\Controller\Script\DeviceDates',
							'action' => 'compute',
						),
					),
				),
                'gem-device-retires-flag' => array(
					'options' => array(
						'route' => 'gem device retires flag [--offset=]',
						'defaults' => array(
							'controller' => 'Gem\Controller\Script\DeviceRetires',
							'action' => 'flag',
						),
					),
				),
                 'gem-device-unretires-flag' => array(
					'options' => array(
						'route' => 'gem device unretires flag [--offset=]',
						'defaults' => array(
							'controller' => 'Gem\Controller\Script\DeviceRetires',
							'action' => 'unflag',
						),
					),
				),
				'gem-data-consistency-check' => array(
					'options' => array(
						'route' => 'gem data-consistency check <type> --site= [--max=] [--dry-run]',
						'defaults' => array(
							'controller' => 'Gem\Controller\Script\DataConsistency',
							'action' => 'check',
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
	'view_helpers' => array(
		'factories' => array(
			'testbed' => 'Gem\View\Helper\TestbedFactory',
            'site' => 'Gem\View\Helper\SiteFactory',
            'labview' => 'Gem\View\Helper\LabviewFactory',
		),
    ),
    'controllers' => array(
        'invokables' => array(
            'Gem\Controller\Index' => 'Gem\Controller\IndexController',
			'Gem\Controller\Assets' => 'Gem\Controller\AssetsController',

			'Gem\Controller\Sites' => 'Gem\Controller\SitesController',
			'Gem\Controller\Labs' => 'Gem\Controller\LabsController',
			'Gem\Controller\Racks' => 'Gem\Controller\RacksController',
			'Gem\Controller\Devices' => 'Gem\Controller\DevicesController',
			'Gem\Controller\DeviceHistory' => 'Gem\Controller\DeviceHistoryController',
			'Gem\Controller\Testbeds' => 'Gem\Controller\TestbedsController',
			'Gem\Controller\TestbedReservations' => 'Gem\Controller\TestbedReservationsController',
			'Gem\Controller\Toss' => 'Gem\Controller\TossController',
        	'Gem\Controller\Catalog' => 'Gem\Controller\CatalogController',
			'Gem\Controller\Labviews' => 'Gem\Controller\LabviewsController',
      	'Gem\Controller\Autogenerated' => 'Gem\Controller\AutogeneratedController',
            'Gem\Controller\Report' => 'Gem\Controller\ReportController',
            'Gem\Controller\Virtuals' => 'Gem\Controller\VirtualsController',

			'Gem\Controller\Script\Tgen' => 'Gem\Controller\Script\TgenController',
			'Gem\Controller\Script\Scan' => 'Gem\Controller\Script\ScanController',
			'Gem\Controller\Script\Sphinx' => 'Gem\Controller\Script\SphinxController',
			'Gem\Controller\Script\Toss' => 'Gem\Controller\Script\TossController',
			'Gem\Controller\Script\Stats' => 'Gem\Controller\Script\StatsController',
			'Gem\Controller\Script\DataPoints' => 'Gem\Controller\Script\DataPointsController',
            'Gem\Controller\Script\Utilizations' => 'Gem\Controller\Script\UtilizationsController',
            'Gem\Controller\Script\Reservations' => 'Gem\Controller\Script\ReservationsController',
        	'Gem\Controller\Script\HrChanges' => 'Gem\Controller\Script\HrChangesController',
            'Gem\Controller\Script\DeviceDates' => 'Gem\Controller\Script\DeviceDatesController',
            'Gem\Controller\Script\DeviceRetires' => 'Gem\Controller\Script\DeviceRetiresController',
        	'Gem\Controller\Script\DataConsistency' => 'Gem\Controller\Script\DataConsistencyController',

			'Gem\Controller\Rest\Assets' => 'Gem\Controller\Rest\AssetsController',
			'Gem\Controller\Rest\Sites' => 'Gem\Controller\Rest\SitesController',
			'Gem\Controller\Rest\Labs' => 'Gem\Controller\Rest\LabsController',
			'Gem\Controller\Rest\Racks' => 'Gem\Controller\Rest\RacksController',
			'Gem\Controller\Rest\Devices' => 'Gem\Controller\Rest\DevicesController',
			'Gem\Controller\Rest\Types' => 'Gem\Controller\Rest\TypesController',
			'Gem\Controller\Rest\RackDataPoints' => 'Gem\Controller\Rest\RackDataPointsController',
            'Gem\Controller\Rest\Virtuals' => 'Gem\Controller\Rest\VirtualsController',
            'Gem\Controller\Rest\ClusterTypes' => 'Gem\Controller\Rest\ClusterTypesController',
            'Gem\Controller\Rest\CalibrationTypes' => 'Gem\Controller\Rest\CalibrationTypesController',
            'Gem\Controller\Rest\LicenseTypes' => 'Gem\Controller\Rest\LicenseTypesController',
            'Gem\Controller\Rest\PortTypes' => 'Gem\Controller\Rest\PortTypesController',
            'Gem\Controller\Rest\PortSpeeds' => 'Gem\Controller\Rest\PortSpeedsController',
            'Gem\Controller\Rest\PointTypes' => 'Gem\Controller\Rest\PointTypesController',
            'Gem\Controller\Rest\ConnectionTypes' => 'Gem\Controller\Rest\ConnectionTypesController',

            'Gem\Controller\Rest\QuickDevices' => 'Gem\Controller\Rest\QuickDevicesController',
			'Gem\Controller\Rest\SphinxDevices' => 'Gem\Controller\Rest\SphinxDevicesController',
			'Gem\Controller\Rest\SphinxVirtuals' => 'Gem\Controller\Rest\SphinxVirtualsController',

            'Gem\Controller\Rest\QuickSites' => 'Gem\Controller\Rest\QuickSitesController',
            'Gem\Controller\Rest\QuickLabs' => 'Gem\Controller\Rest\QuickLabsController',
            'Gem\Controller\Rest\QuickRacks' => 'Gem\Controller\Rest\QuickRacksController',

			'Gem\Controller\Rest\Manufacturers' => 'Gem\Controller\Rest\ManufacturersController',
			'Gem\Controller\Rest\ManufacturerModels' => 'Gem\Controller\Rest\ManufacturerModelsController',
        	'Gem\Controller\Rest\ManufacturerProducts' => 'Gem\Controller\Rest\ManufacturerProductsController',
			'Gem\Controller\Rest\ManufacturerParts' => 'Gem\Controller\Rest\ManufacturerPartsController',

			'Gem\Controller\Rest\LabStats' => 'Gem\Controller\Rest\LabStatsController',
			'Gem\Controller\Rest\LabStorageStats' => 'Gem\Controller\Rest\LabStorageStatsController',
			'Gem\Controller\Rest\RackStats' => 'Gem\Controller\Rest\RackStatsController',

			'Gem\Controller\Rest\DeviceHistory' => 'Gem\Controller\Rest\DeviceHistoryController',
            'Gem\Controller\Rest\DeviceCalibrationHistory' => 'Gem\Controller\Rest\DeviceCalibrationHistoryController',
			'Gem\Controller\Rest\DeviceTgenSpecs' => 'Gem\Controller\Rest\DeviceTgenSpecsController',
			'Gem\Controller\Rest\DeviceTgenFeatures' => 'Gem\Controller\Rest\DeviceTgenFeaturesController',
			'Gem\Controller\Rest\DeviceServerHbas' => 'Gem\Controller\Rest\DeviceServerHbasController',
			'Gem\Controller\Rest\DeviceGeneralSpecs' => 'Gem\Controller\Rest\DeviceGeneralSpecsController',
			'Gem\Controller\Rest\DeviceReservations' => 'Gem\Controller\Rest\DeviceReservationsController',
			'Gem\Controller\Rest\DeviceOwnerTypes' => 'Gem\Controller\Rest\DeviceOwnerTypesController',
            'Gem\Controller\Rest\RackOwnerTypes' => 'Gem\Controller\Rest\RackOwnerTypesController',
            'Gem\Controller\Rest\VirtualHistory' => 'Gem\Controller\Rest\VirtualHistoryController',
            'Gem\Controller\Rest\Reservations' => 'Gem\Controller\Rest\ReservationsController',

			'Gem\Controller\ImportDevices' => 'Gem\Controller\ImportDevicesController',
			'Gem\Controller\Rest\ImportFileTypes' => 'Gem\Controller\Rest\ImportFileTypesController',
			'Gem\Controller\Rest\ImportFileRows' => 'Gem\Controller\Rest\ImportFileRowsController',

			'Gem\Controller\Rest\Testbeds' => 'Gem\Controller\Rest\TestbedsController',
			'Gem\Controller\Rest\Testbeds\Devices' => 'Gem\Controller\Rest\Testbeds\DevicesController',
            'Gem\Controller\Rest\Testbeds\Virtuals' => 'Gem\Controller\Rest\Testbeds\VirtualsController',
			'Gem\Controller\Rest\Testbeds\AvailableDevices' => 'Gem\Controller\Rest\Testbeds\AvailableDevicesController',
            'Gem\Controller\Rest\Testbeds\AvailableVirtuals' => 'Gem\Controller\Rest\Testbeds\AvailableVirtualsController',
			'Gem\Controller\Rest\Testbeds\Users' => 'Gem\Controller\Rest\Testbeds\UsersController',
			'Gem\Controller\Rest\Testbeds\AvailableUsers' => 'Gem\Controller\Rest\Testbeds\AvailableUsersController',
			'Gem\Controller\Rest\Testbeds\Groups' => 'Gem\Controller\Rest\Testbeds\GroupsController',
			'Gem\Controller\Rest\Testbeds\AvailableGroups' => 'Gem\Controller\Rest\Testbeds\AvailableGroupsController',
			'Gem\Controller\Rest\Testbeds\DeviceReservations' => 'Gem\Controller\Rest\Testbeds\DeviceReservationsController',
            'Gem\Controller\Rest\Testbeds\VirtualReservations' => 'Gem\Controller\Rest\Testbeds\VirtualReservationsController',
			'Gem\Controller\Rest\Testbeds\DeviceFields' => 'Gem\Controller\Rest\Testbeds\DeviceFieldsController',
            'Gem\Controller\Rest\Testbeds\VirtualFields' => 'Gem\Controller\Rest\Testbeds\VirtualFieldsController',
			'Gem\Controller\Rest\Testbeds\Labviews' => 'Gem\Controller\Rest\Testbeds\LabviewsController',
			'Gem\Controller\Rest\Testbeds\AvailableLabviews' => 'Gem\Controller\Rest\Testbeds\AvailableLabviewsController',
            'Gem\Controller\Rest\Testbeds\Documents' => 'Gem\Controller\Rest\Testbeds\DocumentsController',

            'Gem\Controller\Rest\Sites\AvailableLabviews' => 'Gem\Controller\Rest\Sites\AvailableLabviewsController',
            'Gem\Controller\Rest\Sites\Labviews' => 'Gem\Controller\Rest\Sites\LabviewsController',

            'Gem\Controller\Rest\Labs\AvailableLabviews' => 'Gem\Controller\Rest\Labs\AvailableLabviewsController',
            'Gem\Controller\Rest\Labs\Labviews' => 'Gem\Controller\Rest\Labs\LabviewsController',

            'Gem\Controller\Rest\Racks\AvailableLabviews' => 'Gem\Controller\Rest\Racks\AvailableLabviewsController',
            'Gem\Controller\Rest\Racks\Labviews' => 'Gem\Controller\Rest\Racks\LabviewsController',

			'Gem\Controller\Rest\Devices\AvailableTestbeds' => 'Gem\Controller\Rest\Devices\AvailableTestbedsController',
            'Gem\Controller\Rest\Devices\AvailableLabviews' => 'Gem\Controller\Rest\Devices\AvailableLabviewsController',
        	'Gem\Controller\Rest\Devices\AvailableContracts' => 'Gem\Controller\Rest\Devices\AvailableContractsController',
			'Gem\Controller\Rest\Devices\Reservations' => 'Gem\Controller\Rest\Devices\ReservationsController',
			'Gem\Controller\Rest\Devices\Testbeds' => 'Gem\Controller\Rest\Devices\TestbedsController',
            'Gem\Controller\Rest\Devices\Projects' => 'Gem\Controller\Rest\Devices\ProjectsController',
            'Gem\Controller\Rest\Devices\Labviews' => 'Gem\Controller\Rest\Devices\LabviewsController',
        	'Gem\Controller\Rest\Devices\Contracts' => 'Gem\Controller\Rest\Devices\ContractsController',

            'Gem\Controller\Rest\Assets\Layouts' => 'Gem\Controller\Rest\Assets\LayoutsController',
            'Gem\Controller\Rest\Assets\Layouts\Assets' => 'Gem\Controller\Rest\Assets\Layouts\AssetsController',
            'Gem\Controller\Rest\Assets\Layouts\Configs' => 'Gem\Controller\Rest\Assets\Layouts\ConfigsController',
            'Gem\Controller\Rest\Assets\Layouts\Objects' => 'Gem\Controller\Rest\Assets\Layouts\ObjectsController',
            'Gem\Controller\Rest\Assets\Layouts\DataPoints' => 'Gem\Controller\Rest\Assets\Layouts\DataPointsController',
            'Gem\Controller\Rest\Assets\LayoutBackgrounds' => 'Gem\Controller\Rest\Assets\LayoutBackgroundsController',
            'Gem\Controller\Rest\Assets\DataPoints' => 'Gem\Controller\Rest\Assets\DataPointsController',
            'Gem\Controller\Rest\Assets\Comments' => 'Gem\Controller\Rest\Assets\CommentsController',

            'Gem\Controller\Rest\Virtuals\AvailableTestbeds' => 'Gem\Controller\Rest\Virtuals\AvailableTestbedsController',
            'Gem\Controller\Rest\Virtuals\AvailableLabviews' => 'Gem\Controller\Rest\Virtuals\AvailableLabviewsController',
            'Gem\Controller\Rest\Virtuals\AvailableDevices' => 'Gem\Controller\Rest\Virtuals\AvailableDevicesController',
            'Gem\Controller\Rest\Virtuals\Reservations' => 'Gem\Controller\Rest\Virtuals\ReservationsController',
            'Gem\Controller\Rest\Virtuals\Testbeds' => 'Gem\Controller\Rest\Virtuals\TestbedsController',
            'Gem\Controller\Rest\Virtuals\Projects' => 'Gem\Controller\Rest\Virtuals\ProjectsController',
            'Gem\Controller\Rest\Virtuals\Labviews' => 'Gem\Controller\Rest\Virtuals\LabviewsController',
            'Gem\Controller\Rest\Virtuals\Devices' => 'Gem\Controller\Rest\Virtuals\DevicesController',

            'Gem\Controller\Rest\DataPointTypes\DataPointTypes' => 'Gem\Controller\Rest\DataPointTypes\DataPointTypesController',
            'Gem\Controller\Rest\DataPointTypes' => 'Gem\Controller\Rest\DataPointTypesController',

			'Gem\Controller\Rest\OsBrands' => 'Gem\Controller\Rest\OsBrandsController',
			'Gem\Controller\Rest\OsVersions' => 'Gem\Controller\Rest\OsVersionsController',

			'Gem\Controller\Rest\CpuManufacturers' => 'Gem\Controller\Rest\CpuManufacturersController',
			'Gem\Controller\Rest\CpuFamilies' => 'Gem\Controller\Rest\CpuFamiliesController',
			'Gem\Controller\Rest\CpuModels' => 'Gem\Controller\Rest\CpuModelsController',

			'Gem\Controller\Rest\HbaManufacturers' => 'Gem\Controller\Rest\HbaManufacturersController',
			'Gem\Controller\Rest\HbaTypes' => 'Gem\Controller\Rest\HbaTypesController',
			'Gem\Controller\Rest\HbaSpeeds' => 'Gem\Controller\Rest\HbaSpeedsController',
			'Gem\Controller\Rest\TgenSpeeds' => 'Gem\Controller\Rest\TgenSpeedsController',

			'Gem\Controller\Rest\TossStorages' => 'Gem\Controller\Rest\TossStoragesController',
			'Gem\Controller\Rest\TossStorageStats' => 'Gem\Controller\Rest\TossStorageStatsController',
			'Gem\Controller\Rest\TossStorageDevices' => 'Gem\Controller\Rest\TossStorageDevicesController',
			'Gem\Controller\Rest\TossStorageDeviceStats' => 'Gem\Controller\Rest\TossStorageDeviceStatsController',
			'Gem\Controller\Rest\TossRequests' => 'Gem\Controller\Rest\TossRequestsController',
			'Gem\Controller\Rest\TossRequestStats' => 'Gem\Controller\Rest\TossRequestStatsController',
			'Gem\Controller\Rest\TossRequestDevices' => 'Gem\Controller\Rest\TossRequestDevicesController',
			'Gem\Controller\Rest\TossRequestTasks' => 'Gem\Controller\Rest\TossRequestTasksController',

            'Gem\Controller\Rest\Labviews' => 'Gem\Controller\Rest\LabviewsController',
            'Gem\Controller\Rest\Labviews\Sites' => 'Gem\Controller\Rest\Labviews\SitesController',
            'Gem\Controller\Rest\Labviews\Labs' => 'Gem\Controller\Rest\Labviews\LabsController',
            'Gem\Controller\Rest\Labviews\Racks' => 'Gem\Controller\Rest\Labviews\RacksController',
            'Gem\Controller\Rest\Labviews\Devices' => 'Gem\Controller\Rest\Labviews\DevicesController',
            'Gem\Controller\Rest\Labviews\Virtuals' => 'Gem\Controller\Rest\Labviews\VirtualsController',
            'Gem\Controller\Rest\Labviews\Users' => 'Gem\Controller\Rest\Labviews\UsersController',
            'Gem\Controller\Rest\Labviews\Groups' => 'Gem\Controller\Rest\Labviews\GroupsController',
            'Gem\Controller\Rest\Labviews\Depts' => 'Gem\Controller\Rest\Labviews\DeptsController',
            'Gem\Controller\Rest\Labviews\Projects' => 'Gem\Controller\Rest\Labviews\ProjectsController',
            'Gem\Controller\Rest\Labviews\Testbeds' => 'Gem\Controller\Rest\Labviews\TestbedsController',
        	'Gem\Controller\Rest\Labviews\Contracts' => 'Gem\Controller\Rest\Labviews\ContractsController',
            'Gem\Controller\Rest\Labviews\AvailableSites' => 'Gem\Controller\Rest\Labviews\AvailableSitesController',
            'Gem\Controller\Rest\Labviews\AvailableLabs' => 'Gem\Controller\Rest\Labviews\AvailableLabsController',
            'Gem\Controller\Rest\Labviews\AvailableRacks' => 'Gem\Controller\Rest\Labviews\AvailableRacksController',
            'Gem\Controller\Rest\Labviews\AvailableDevices' => 'Gem\Controller\Rest\Labviews\AvailableDevicesController',
            'Gem\Controller\Rest\Labviews\AvailableVirtuals' => 'Gem\Controller\Rest\Labviews\AvailableVirtualsController',
            'Gem\Controller\Rest\Labviews\AvailableUsers' => 'Gem\Controller\Rest\Labviews\AvailableUsersController',
            'Gem\Controller\Rest\Labviews\AvailableGroups' => 'Gem\Controller\Rest\Labviews\AvailableGroupsController',
            'Gem\Controller\Rest\Labviews\AvailableDepts' => 'Gem\Controller\Rest\Labviews\AvailableDeptsController',
            'Gem\Controller\Rest\Labviews\AvailableProjects' => 'Gem\Controller\Rest\Labviews\AvailableProjectsController',
            'Gem\Controller\Rest\Labviews\AvailableTestbeds' => 'Gem\Controller\Rest\Labviews\AvailableTestbedsController',
        	'Gem\Controller\Rest\Labviews\AvailableContracts' => 'Gem\Controller\Rest\Labviews\AvailableContractsController',

			'Gem\Controller\Export\Devices' => 'Gem\Controller\Export\DevicesController',
			'Gem\Controller\Export\DeviceHistory' => 'Gem\Controller\Export\DeviceHistoryController',
			'Gem\Controller\Export\RackStats' => 'Gem\Controller\Export\RackStatsController',
			'Gem\Controller\Export\LabStats' => 'Gem\Controller\Export\LabStatsController',
			'Gem\Controller\Export\LabStorageStats' => 'Gem\Controller\Export\LabStorageStatsController',
			'Gem\Controller\Export\Sites' => 'Gem\Controller\Export\SitesController',
			'Gem\Controller\Export\Labs' => 'Gem\Controller\Export\LabsController',
			'Gem\Controller\Export\Racks' => 'Gem\Controller\Export\RacksController',
			'Gem\Controller\Export\DeviceTgenSpecs' => 'Gem\Controller\Export\DeviceTgenSpecsController',
			'Gem\Controller\Export\DeviceTgenFeatures' => 'Gem\Controller\Export\DeviceTgenFeaturesController',
			'Gem\Controller\Export\DeviceServerHbas' => 'Gem\Controller\Export\DeviceServerHbasController',
			'Gem\Controller\Export\DeviceGeneralSpecs' => 'Gem\Controller\Export\DeviceGeneralSpecsController',
			'Gem\Controller\Export\Testbeds' => 'Gem\Controller\Export\TestbedsController',
			'Gem\Controller\Export\SphinxDevices' => 'Gem\Controller\Export\SphinxDevicesController',
			'Gem\Controller\Export\SphinxDevicesFull' => 'Gem\Controller\Export\SphinxDevicesFullController',
            'Gem\Controller\Export\QuickDevices' => 'Gem\Controller\Export\QuickDevicesController',
            'Gem\Controller\Export\VirtualHistory' => 'Gem\Controller\Export\VirtualHistoryController',
            'Gem\Controller\Export\SphinxVirtuals' => 'Gem\Controller\Export\SphinxVirtualsController',

			'Gem\Controller\Export\Testbeds\Devices' => 'Gem\Controller\Export\Testbeds\DevicesController',
			'Gem\Controller\Export\Testbeds\Users' => 'Gem\Controller\Export\Testbeds\UsersController',
			'Gem\Controller\Export\Testbeds\Groups' => 'Gem\Controller\Export\Testbeds\GroupsController',
			'Gem\Controller\Export\Testbeds\DeviceReservations' => 'Gem\Controller\Export\Testbeds\DeviceReservationsController',
			'Gem\Controller\Export\Testbeds\Labviews' => 'Gem\Controller\Export\Testbeds\LabviewsController',
            'Gem\Controller\Export\Testbeds\AvailableLabviews' => 'Gem\Controller\Export\Testbeds\AvailableLabviewsController',

			'Gem\Controller\Export\TossStorageDevices' => 'Gem\Controller\Export\TossStorageDevicesController',
			'Gem\Controller\Export\TossRequestDevices' => 'Gem\Controller\Export\TossRequestDevicesController',
			'Gem\Controller\Export\TossRequestStats' => 'Gem\Controller\Export\TossRequestStatsController',

            'Gem\Controller\Export\Assets\Comments' => 'Gem\Controller\Export\Assets\CommentsController',

            'Gem\Controller\Export\Sites\Labviews' => 'Gem\Controller\Export\Sites\LabviewsController',
            'Gem\Controller\Export\Labs\Labviews' => 'Gem\Controller\Export\Labs\LabviewsController',
            'Gem\Controller\Export\Racks\Labviews' => 'Gem\Controller\Export\Racks\LabviewsController',

            'Gem\Controller\Export\Devices\Reservations' => 'Gem\Controller\Export\Devices\ReservationsController',
            'Gem\Controller\Export\Devices\Testbeds' => 'Gem\Controller\Export\Devices\TestbedsController',
            'Gem\Controller\Export\Devices\Projects' => 'Gem\Controller\Export\Devices\ProjectsController',
            'Gem\Controller\Export\Devices\Labviews' => 'Gem\Controller\Export\Devices\LabviewsController',
        	'Gem\Controller\Export\Devices\Contracts' => 'Gem\Controller\Export\Devices\ContractsController',

            'Gem\Controller\Export\Virtuals\Reservations' => 'Gem\Controller\Export\Virtuals\ReservationsController',
            'Gem\Controller\Export\Virtuals\Testbeds' => 'Gem\Controller\Export\Virtuals\TestbedsController',
            'Gem\Controller\Export\Virtuals\Projects' => 'Gem\Controller\Export\Virtuals\ProjectsController',
            'Gem\Controller\Export\Virtuals\Labviews' => 'Gem\Controller\Export\Virtuals\LabviewsController',
        	'Gem\Controller\Export\Virtuals\Contracts' => 'Gem\Controller\Export\Virtuals\ContractsController',
            'Gem\Controller\Export\Virtuals\Devices' => 'Gem\Controller\Export\Virtuals\DevicesController',

            'Gem\Controller\Export\Labviews' => 'Gem\Controller\Export\LabviewsController',
            'Gem\Controller\Export\Labviews\Sites' => 'Gem\Controller\Export\Labviews\SitesController',
            'Gem\Controller\Export\Labviews\Labs' => 'Gem\Controller\Export\Labviews\LabsController',
            'Gem\Controller\Export\Labviews\Racks' => 'Gem\Controller\Export\Labviews\RacksController',
            'Gem\Controller\Export\Labviews\Devices' => 'Gem\Controller\Export\Labviews\DevicesController',
            'Gem\Controller\Export\Labviews\Users' => 'Gem\Controller\Export\Labviews\UsersController',
            'Gem\Controller\Export\Labviews\Groups' => 'Gem\Controller\Export\Labviews\GroupsController',
            'Gem\Controller\Export\Labviews\Depts' => 'Gem\Controller\Export\Labviews\DeptsController',
            'Gem\Controller\Export\Labviews\Projects' => 'Gem\Controller\Export\Labviews\ProjectsController',
            'Gem\Controller\Export\Labviews\Testbeds' => 'Gem\Controller\Export\Labviews\TestbedsController',
        	'Gem\Controller\Export\Labviews\Contracts' => 'Gem\Controller\Export\Labviews\ContractsController',
            'Gem\Controller\Export\Labviews\Virtuals' => 'Gem\Controller\Export\Labviews\VirtualsController',
        ),
    ),
	'controller_plugins' => array(
		'factories' => array(
			'testbed' => 'Gem\Controller\Plugin\TestbedFactory',
		),
        'invokables' => array(
            'labview' => 'Gem\Controller\Plugin\Labview',
        ),
	),
	'module_layouts' => array(
		'Gem' => 'layout/layout.phtml',
	),
	'log' => array(
		'sphinx_logger' => array(
			'writers' => array(
                array(
                    'name' => 'stream',
                    'priority' => 1,
                    'options' => array(
                        'stream' => 'data/logs/sphinx_reindex.log',
                    ),
                    'filters' => array(
                        array(
                            'name' => 'priority',
                            'options' => array(
                                'priority' => E_ALL & ~E_STRICT & ~E_WARNING & ~E_NOTICE,
                            ),
                        ),
                    ),
                ),
            ),
		),
	),
	// define the config for the sidebar for this module
	'sidebar' => array(
        'catalog' => array(
            'template' => 'menu/sidebar/catalog',
            'order' => 4
        ),
        'labview' => array(
            'template' => 'menu/sidebar/labview',
            'order' => 5
        ),
		'testbed' => array(
            'template' => 'menu/sidebar/testbed',
            'order' => 6
        ),
        'inventory' => array(
            'template' => 'menu/sidebar/inventory',
			'options' => array(
				// set to true to enable event trigger data loading just for this instance
				'load' => true,
			),
            'order' => 8
        ),
		'toss' => array(
            'template' => 'menu/sidebar/toss',
            'order' => 12
        ),
	),
	// define the config for the topbar for this module
	'topbar' => array(
		'testbed' => array(
            'template' => 'menu/topbar/testbed',
            'order' => 1,
        ),
	),
	// define the config for the breadcrumb bars for this module
	'breadcrumb' => array(
		//'search' => 'menu/breadcrumb/search',
	),
	// define the config for the shortcut for this module
	'shortcut' => array(
		'shortcut' => 'shortcut/shortcut'
	),
	'dashboard_widgets' => array(
		'widgets' => array(
			'template' => 'dashboard/widgets',
			'options' => array(
				'load' => true
			),
            'order' => 0
		),
		'intro' => array(
            'template' => 'dashboard/intro',
            'order' => 1
        ),
	),
    'search_widgets' => array(
        'devices' => array(
            'template' => 'search/devices',
            'order' => 2,
        ),
        'virtuals' => array(
            'template' => 'search/virtuals',
            'order' => 3,
            'options' => array(
                'security' => array(
                    'controller' => 'Gem::Virtuals',
                    'action' => 'index',
                ),
            ),
        ),
    ),
	'testbed' => array(
		'session_field' => 'user_testbed',
        'docdir' => 'public/docs/testbeds',
	),
	'auth' => array(
		'gem_ns' => 'Gem'
	),
    'api' => array(
        'services' => array(
            'virtual' => 'Gem\Service\Virtual',
            'device' => 'Gem\Service\Device',
            'device-type' => 'Gem\Service\DeviceType',
            'site' => 'Gem\Service\Site',
            'lab' => 'Gem\Service\Lab',
            'rack' => 'Gem\Service\Rack',
            'manufacturer' => 'Gem\Service\Manufacturer',
            'model' => 'Gem\Service\Model',
        ),
    ),
    'documentation' => array(
        __DIR__ . '/documentation.config.php'
    ),
    'table' => array(
        __DIR__ . '/table.config.php'
    ),
    'excel' => array(
        'templates' => array(
            'master' => __DIR__ . '/../data/excel/templates/master.xlsx',
            'change' => __DIR__ . '/../data/excel/templates/change.xlsx',
        ),
    ),
);
?>
