<?php
return array(
	'log' => array(
		'auth_logger' => array(
			'writers' => array(
                array(
                    'name' => 'stream',
                    'priority' => 1,
                    'options' => array(
                        'stream' => 'data/logs/auth.log',
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
    'router' => array(
        'routes' => array(
            // The following is a route to simplify getting started creating
            // new controllers and actions without needing to create a new
            // module. Simply drop new controllers in, and you can access them
            // using the path //:controller/:action
            'login' => array(
                'type'    => 'Literal',
                'options' => array(
                    'route'    => '/login',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Auth\Controller',
                        'controller'    => 'Login',
                        'action'        => 'index',
                    ),
                ),
                'may_terminate' => true,
            ),
            'logout' => array(
                'type'    => 'Literal',
                'options' => array(
                    'route'    => '/logout',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Auth\Controller',
                        'controller'    => 'Logout',
                        'action'        => 'index',
                    ),
                ),
                'may_terminate' => true,
            ),
            'permission-error' => array(
                'type'    => 'Literal',
                'options' => array(
                    'route'    => '/permission-error',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Auth\Controller',
                        'controller'    => 'PermissionError',
                        'action'        => 'index',
                    ),
                ),
                'may_terminate' => true,
            ),
			'auth' => array(
                'type'    => 'Literal',
                'options' => array(
                    'route'    => '/auth',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Auth\Controller',
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
								'id' 			=> '[0-9\-]+',
							),
							'defaults' => array(
								'__NAMESPACE__' => 'Auth\Controller\Rest',
								'action' => false,
							),
						),
					),
					'default' => array(
						'type'	=> 'Segment',
						'options' => array(
							'route'	=> '/:controller/[:action[/:id]]',
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
				'ldap-load-user' => array(
					'options' => array(
						'route' => 'ldap load [--username=] [--password=] <user>',
						'defaults' => array(
							'controller' => 'Auth\Controller\Script',
							'action' => 'load',
						),
					),
				),
				'ldap-load-all-user' => array(
					'options' => array(
						'route' => 'ldap load-all [--username=] [--password=] [--start=] [--end=] [--cutoff=]',
						'defaults' => array(
							'controller' => 'Auth\Controller\Script',
							'action' => 'load-all',
						),
					),
				),
                'ldap-load-group' => array(
					'options' => array(
						'route' => 'ldap load-group [--username=] [--password=] <group>',
						'defaults' => array(
							'controller' => 'Auth\Controller\Script',
							'action' => 'load-group',
						),
					),
				),
				'ldap-load-all-group' => array(
					'options' => array(
						'route' => 'ldap load-all-group [--username=] [--password=] [--start=] [--end=] [--cutoff=]',
						'defaults' => array(
							'controller' => 'Auth\Controller\Script',
							'action' => 'load-all-group',
						),
					),
				),
                'ldap-clean' => array(
                    'options' => array(
						'route' => 'ldap clean --cutoff=',
						'defaults' => array(
							'controller' => 'Auth\Controller\Script',
							'action' => 'clean',
						),
					),
                ),
                'auth-role-assign' => array(
                    'options' => array(
                        'route' => 'auth role assign --domain= --username= --role=',
                        'defaults' => array(
                            'controller' => 'Auth\Controller\Script\Security',
                            'action' => 'assign-role',
                        ),
                    ),
                ),
                'auth-local-account-create' => array(
                    'options' => array(
                        'route' => 'auth local account create --username= --password= --email= --first-name= --last-name=',
                        'defaults' => array(
                            'controller' => 'Auth\Controller\Script\Security',
                            'action' => 'create-local-user',
                        ),
                    ),
                ),
                'auth-local-account-update-password' => array(
                    'options' => array(
                        'route' => 'auth local account update password --username= --password=',
                        'defaults' => array(
                            'controller' => 'Auth\Controller\Script\Security',
                            'action' => 'update-local-user-password',
                        ),
                    ),
                ),
			),
		),
	),
	'service_manager' => array(
		'factories' => array(
			'auth' => 'Els\Authentication\AuthenticationServiceFactory',
			'auth_adapter' => 'Els\Authentication\Adapter\LdapFactory',
			'auth_storage' => 'Els\Authentication\Storage\SessionFactory',
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
		'invokables' => array(
            'authentication' => 'Els\View\Helper\Authentication',
			'authorization' => 'Els\View\Helper\Authorization',
			'profile' => 'Els\View\Helper\Profile',
		),
    ),
    'controllers' => array(
        'invokables' => array(
            'Auth\Controller\Login'				=> 'Auth\Controller\LoginController',
            'Auth\Controller\Logout' 			=> 'Auth\Controller\LogoutController',
            'Auth\Controller\PermissionError' 	=> 'Auth\Controller\PermissionErrorController',
            'Auth\Controller\Security'          => 'Auth\Controller\SecurityController',
			'Auth\Controller\Script'			=> 'Auth\Controller\ScriptController',
			
            'Auth\Controller\Script\Security' => 'Auth\Controller\Script\SecurityController',
            
            'Auth\Controller\Rest\Roles' => 'Auth\Controller\Rest\RolesController',
			'Auth\Controller\Rest\Permissions' => 'Auth\Controller\Rest\PermissionsController',
			'Auth\Controller\Rest\Resources' => 'Auth\Controller\Rest\ResourcesController',
			'Auth\Controller\Rest\Users' => 'Auth\Controller\Rest\UsersController',
			'Auth\Controller\Rest\Groups' => 'Auth\Controller\Rest\GroupsController',
			'Auth\Controller\Rest\ControllerResources' => 'Auth\Controller\Rest\ControllerResourcesController',
			'Auth\Controller\Rest\ExcludeRoles' => 'Auth\Controller\Rest\ExcludeRolesController',
            
			'Auth\Controller\Rest\AvailableResources' => 'Auth\Controller\Rest\AvailableResourcesController',
			'Auth\Controller\Rest\AvailableRoles' => 'Auth\Controller\Rest\AvailableRolesController',
            'Auth\Controller\Rest\AvailableExcludeRoles' => 'Auth\Controller\Rest\AvailableExcludeRolesController',
			'Auth\Controller\Rest\AvailablePermissions' => 'Auth\Controller\Rest\AvailablePermissionsController',
			'Auth\Controller\Rest\AvailableUsers' => 'Auth\Controller\Rest\AvailableUsersController',
			'Auth\Controller\Rest\AvailableGroups' => 'Auth\Controller\Rest\AvailableGroupsController',
        ),
    ),
    'controller_plugins' => array(
        'invokables' => array(
            'authentication'	=> 'Els\Controller\Plugin\Authentication',
        ),
		'factories' => array(
			'authorization' => 'Els\Controller\Plugin\AuthorizationFactory',
			'profile' => 'Els\Controller\Plugin\ProfileFactory',
			'sso' => 'Auth\Controller\Plugin\SsoFactory',
		),
    ),
	'auth' => array(
		'adapter' 		=> 'auth_adapter',
		'storage' 		=> 'auth_storage',
		// the route to redirect to when logging in (1st precedence)
		'landing_route' => 'application',
		// the url to redirect to when logging in (2nd precedence)
		'landing_url' 	=> '/',
		'authorization_field' => 'authorize',
        // decommission_message
        'decommission_message' => '',
        // decommission_url
        'decommission_url' => '',        
	),
	'sso' => array(
		'session_field' => 'permission_server',
		'content_field' => 'sso',
		'loaders' => array(
			'corp' => array(
				'db' => 'db',
				'select' => "
					SET SESSION group_concat_max_len = 10240;
					SELECT
                        U.id AS `user_id`,
                        U.username,
                        U.email,
                        U.phone,
                        U.display_name,
                        U.full_name,
                        U.first_name,
                        U.last_name,
                        D.`number` AS `department_number`,
                        D.`name` AS `department`,                        
                        UP.picture AS `picture`,
                        U.`number` AS `employee_id`,
                        U.`organization`,
						GROUP_CONCAT(DISTINCT G.`name` ORDER BY G.`name` ASC SEPARATOR ',') AS `groups`
					FROM `corp_hr`.`hr_users` U
                        LEFT JOIN `corp_hr`.`hr_depts` D ON U.`department_id` = D.`id`
						LEFT JOIN `corp_hr`.`hr_user_group_links` UGL ON U.`id` = UGL.`user_id`
						LEFT JOIN `corp_hr`.`hr_groups` G ON UGL.`group_id` = G.`id`
                        LEFT JOIN `corp_hr`.`hr_user_pictures` UP ON U.`id` = UP.`user_id`
					WHERE U.`id` = :user_id
					GROUP BY U.`id`;
				",
			),
		),
	),
	'session' => array(
		// this is the session namespace solely for the authentication.  We will continue to use 'els' so that this will work across MEMCACHED for Elstools
		'namespace' => 'els',
	),
	'module_layouts' => array(
		'Auth' => 'layout/layout.phtml',
	),
    // define the config for the sidebar for this module
	'sidebar' => array(
		'security' => array(
            'template' => 'menu/sidebar/security',
            'order' => 2
        ),
	),
);
?>