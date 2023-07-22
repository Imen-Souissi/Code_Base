<?php
namespace Auth\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Log\Logger;
use Zend\Form\Factory;

use Auth\Model\SecurityRoles;
use Auth\Model\SecurityPermissions;
use Auth\Model\SecurityResources;
use Auth\Model\SecurityResourcePermissionLinks;
use Auth\Model\SecurityRolePermissionLinks;
use Auth\Model\SecurityRoleExcludeRoles;
use Auth\Model\SecurityUserRoleLinks;
use Auth\Model\SecurityGroupRoleLinks;

class SecurityController extends AbstractActionController {	
	protected function buildRoleAddForm() {
		$factory = new Factory();
		$trimfilter = new \Zend\Filter\StringTrim();
		
		$fields = array('name', 'description', 'rights_level');
		$elements = array();
		$input_filter = array();
		
		foreach($fields as $field) {
			$elements[] = array(
				'spec' => array(
					'type' => 'Zend\Form\Element\Text',
					'name' => $field,
				),
			);
			$input_filter[$field] = array(
				// ensure that the callback validator will be trigger even if the fields are empty
				'continue_if_empty' => true,
				'validators' => array(),
				'filters' => array(
					$trimfilter 
				),
			);
		}
		
		$input_filter['name']['validators'][] = new \Zend\Validator\NotEmpty(array(
			'message' => 'Please provide a Name'
		));		
		
		return $factory->createForm(array(
			'hydrator' => 'Zend\Stdlib\Hydrator\ArraySerializable',
			'elements' => $elements,
			'input_filter' => $input_filter,
		));
	}
	
	protected function buildRoleEditForm() {
		return $this->buildRoleAddForm();
	}
	
	protected function buildPermissionAddForm() {
		$factory = new Factory();
		$trimfilter = new \Zend\Filter\StringTrim();
		
		$fields = array('name', 'description');
		$elements = array();
		$input_filter = array();
		
		foreach($fields as $field) {
			$elements[] = array(
				'spec' => array(
					'type' => 'Zend\Form\Element\Text',
					'name' => $field,
				),
			);
			$input_filter[$field] = array(
				// ensure that the callback validator will be trigger even if the fields are empty
				'continue_if_empty' => true,
				'validators' => array(),
				'filters' => array(
					$trimfilter 
				),
			);
		}
		
		$input_filter['name']['validators'][] = new \Zend\Validator\NotEmpty(array(
			'message' => 'Please provide a Name'
		));		
		
		return $factory->createForm(array(
			'hydrator' => 'Zend\Stdlib\Hydrator\ArraySerializable',
			'elements' => $elements,
			'input_filter' => $input_filter,
		));
	}
	
	protected function buildPermissionEditForm() {
		return $this->buildPermissionAddForm();
	}
	
	protected function buildResourceAddForm() {
		$factory = new Factory();
		$trimfilter = new \Zend\Filter\StringTrim();
		
		$fields = array('controller', 'action');
		$elements = array();
		$input_filter = array();
		
		foreach($fields as $field) {
			$elements[] = array(
				'spec' => array(
					'type' => 'Zend\Form\Element\Text',
					'name' => $field,
				),
			);
			$input_filter[$field] = array(
				// ensure that the callback validator will be trigger even if the fields are empty
				'continue_if_empty' => true,
				'validators' => array(),
				'filters' => array(
					$trimfilter 
				),
			);
		}
		
		$input_filter['controller']['validators'][] = new \Zend\Validator\NotEmpty(array(
			'message' => 'Please provide a Controller'
		));
		$input_filter['action']['validators'][] = new \Zend\Validator\NotEmpty(array(
			'message' => 'Please provide an Action'
		));
		
		return $factory->createForm(array(
			'hydrator' => 'Zend\Stdlib\Hydrator\ArraySerializable',
			'elements' => $elements,
			'input_filter' => $input_filter,
		));
	}
	
	protected function buildResourceEditForm() {
		return $this->buildResourceAddForm();
	}
	
	public function indexAction() {
		return new ViewModel();
	}
	
	public function rolesAction() {
		return new ViewModel();
	}
	
	public function rolesAddAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$form = $this->buildRoleAddForm();
		$error = "";
		
		if($this->getRequest()->isPost()) {
			$form->setData($this->params()->fromPost());
			
			if($form->isValid()) {
				$security_roles_mdl = SecurityRoles::factory($sm);
				$con = $db->getDriver()->getConnection();
				$post = $form->getData();
				
				try {
					$con->beginTransaction();
					
					// check if this name is used
					$role = $security_roles_mdl->get(array(
						'name' => $post['name']
					));
					if($role !== false) {
						$error = "Role name already exists";
						throw new \Exception($error);
					}
					
					$id = $security_roles_mdl->insert(
						$post['name'],
						$post['description'],
						$post['rights_level']
					);
					
					$con->commit();
				} catch(\Exception $e) {
					$con->rollback();
						
					$p = $e->getPrevious();
					if($p) {
						$e = $p;
					}
					
					if(empty($error)) {
						$error = "Unable to complete adding role, please try again";
					}
					$logger->log(Logger::ERR, "unable to add role: " . $e->getMessage());
					$logger->log(Logger::ERR, $e->getTraceAsString());
				}
			} else {
				$messages = $form->getMessages();
				foreach($messages as $field => $msg) {
					$error = array_shift(array_values($msg));
					break;
				}
			}
		}
		
		if(!empty($error)) {
			$this->getResponse()->setStatusCode(500);
			return new JsonModel(array(
				'error' => $error
			));
		}
		
		return new JsonModel(array(
			'id' => $id
		));
	}
	
	public function rolesEditAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$form = $this->buildRoleEditForm();
		$error = "";
		$success = "";
		$post = $this->params()->fromPost();
		$id = $this->params()->fromRoute('id');
		
		$security_roles_mdl = SecurityRoles::factory($sm);
		$role = $security_roles_mdl->get($id);
		
		$view = new ViewModel(array(
			'id' => $id
		));
		
		if($role === false) {
			$view->setTemplate('security/invalid-role');
		} else if($this->getRequest()->isPost()) {
			$form->setData($this->params()->fromPost());
			
			if($form->isValid()) {
				$con = $db->getDriver()->getConnection();
				$post = $form->getData();
				
				try {
					$con->beginTransaction();
					
					// check if this name is used
					$ex_role = $security_roles_mdl->getadv(array(
						'name' => $post['name'],
						'!id' => $id
					));
					if($ex_role !== false) {
						$error = "Role name already exists";
						throw new \Exception($error);
					}
					
					$security_roles_mdl->update($id, array(
						'name' => $post['name'],
						'description' => $post['description'],
						'rights_level' => $post['rights_level']
					));
					
					$con->commit();
					
					$success = "Successfully updated role";
				} catch(\Exception $e) {
					$con->rollback();
						
					$p = $e->getPrevious();
					if($p) {
						$e = $p;
					}
					
					if(empty($error)) {
						$error = "Unable to complete updating role, please try again";
					}
					$logger->log(Logger::ERR, "unable to update role: " . $e->getMessage());
					$logger->log(Logger::ERR, $e->getTraceAsString());
				}
			}
		} else {
			$post = $role->getArrayCopy();
		}
		
		$view->setVariables(array(
			'form' => $form,
			'error' => $error,
			'success' => $success,
			'post' => $post,
			'role' => ($role !== false) ? $role->getArrayCopy() : array()
		));
		
		return $view;
	}
	
	public function rolesDeleteAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$error = "";
		$id = $this->params()->fromRoute('id');
		
		$security_roles_mdl = SecurityRoles::factory($sm);
		
		if($this->getRequest()->isPost()) {			
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
				$security_roles_mdl->delete($id);
				
				$con->commit();
			} catch(\Exception $e) {
				$con->rollback();
					
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to complete deleting role, please try again";
				}
				$logger->log(Logger::ERR, "unable to delete role: " . $e->getMessage());
				$logger->log(Logger::ERR, $e->getTraceAsString());
			}
		}
		
		if(!empty($error)) {
			$this->getResponse()->setStatusCode(500);
			return new JsonModel(array(
				'error' => $error
			));
		}
		
		return new JsonModel(array(
			'id' => $id
		));
	}
		
	public function rolesAssignPermissionsAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$error = "";
		$id = $this->params()->fromRoute('id');
		$security_role_permission_links_mdl = SecurityRolePermissionLinks::factory($sm);
		
		if($this->getRequest()->isPost()) {
			$permissions = $this->params()->fromPost('permissions');
			$permissions = split(',', $permissions);
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
				if(count($permissions) > 0) {
					foreach($permissions as $permission_id) {
						if(!$security_role_permission_links_mdl->exists(array(
							'role_id' => $id,
							'permission_id' => $permission_id
						))) {
							$security_role_permission_links_mdl->insert($id, $permission_id);
						}
					}
				}
				
				$con->commit();
			} catch(\Exception $e) {
				$con->rollback();
				
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to complete assigning permissions, please try again";
				}
				$logger->log(Logger::ERR, "unable to assign permissions: " . $e->getMessage());
				$logger->log(Logger::ERR, $e->getTraceAsString());
			}
		}
		
		if(!empty($error)) {
			$this->getResponse()->setStatusCode(500);
			return new JsonModel(array(
				'error' => $error
			));
		}
		
		return new JsonModel(array(
			'id' => $id
		));
	}
	
	public function rolesUnassignPermissionsAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$error = "";
		$id = $this->params()->fromRoute('id');
		$security_role_permission_links_mdl = SecurityRolePermissionLinks::factory($sm);
		
		if($this->getRequest()->isPost()) {
			$permission_id = $this->params()->fromPost('permission_id');
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
				$security_role_permission_links_mdl->delete(array(
					'permission_id' => $permission_id,
					'role_id' => $id
				));
				
				$con->commit();
			} catch(\Exception $e) {
				$con->rollback();
				
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to complete unassigning permissions, please try again";
				}
				$logger->log(Logger::ERR, "unable to unassign permissions: " . $e->getMessage());
				$logger->log(Logger::ERR, $e->getTraceAsString());
			}
		}
		
		if(!empty($error)) {
			$this->getResponse()->setStatusCode(500);
			return new JsonModel(array(
				'error' => $error
			));
		}
		
		return new JsonModel(array(
			'id' => $id
		));
	}
	
	public function rolesAssignUsersAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$error = "";
		$id = $this->params()->fromRoute('id');
		$security_user_role_links_mdl = SecurityUserRoleLinks::factory($sm);
		
		if($this->getRequest()->isPost()) {
			$users = $this->params()->fromPost('users');
			$users = split(',', $users);
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
				if(count($users) > 0) {
					foreach($users as $user_id) {
						if(!$security_user_role_links_mdl->exists(array(
							'role_id' => $id,
							'user_id' => $user_id
						))) {
							$security_user_role_links_mdl->insert($user_id, $id);
						}
					}
				}
				
				$con->commit();
			} catch(\Exception $e) {
				$con->rollback();
				
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to complete assigning users, please try again";
				}
				$logger->log(Logger::ERR, "unable to assign users: " . $e->getMessage());
				$logger->log(Logger::ERR, $e->getTraceAsString());
			}
		}
		
		if(!empty($error)) {
			$this->getResponse()->setStatusCode(500);
			return new JsonModel(array(
				'error' => $error
			));
		}
		
		return new JsonModel(array(
			'id' => $id
		));
	}
	
	public function rolesUnassignUsersAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$error = "";
		$id = $this->params()->fromRoute('id');
		$security_user_role_links_mdl = SecurityUserRoleLinks::factory($sm);
		
		if($this->getRequest()->isPost()) {
			$user_id = $this->params()->fromPost('user_id');
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
				$security_user_role_links_mdl->delete(array(
					'user_id' => $user_id,
					'role_id' => $id
				));
				
				$con->commit();
			} catch(\Exception $e) {
				$con->rollback();
				
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to complete unassigning users, please try again";
				}
				$logger->log(Logger::ERR, "unable to unassign users: " . $e->getMessage());
				$logger->log(Logger::ERR, $e->getTraceAsString());
			}
		}
		
		if(!empty($error)) {
			$this->getResponse()->setStatusCode(500);
			return new JsonModel(array(
				'error' => $error
			));
		}
		
		return new JsonModel(array(
			'id' => $id
		));
	}
	
	public function rolesAssignGroupsAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$error = "";
		$id = $this->params()->fromRoute('id');
		$security_group_role_links_mdl = SecurityGroupRoleLinks::factory($sm);
		
		if($this->getRequest()->isPost()) {
			$groups = $this->params()->fromPost('groups');
			$groups = split(',', $groups);
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
				if(count($groups) > 0) {
					foreach($groups as $group_id) {
						if(!$security_group_role_links_mdl->exists(array(
							'role_id' => $id,
							'group_id' => $group_id
						))) {
							$security_group_role_links_mdl->insert($group_id, $id);
						}
					}
				}
				
				$con->commit();
			} catch(\Exception $e) {
				$con->rollback();
				
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to complete assigning groups, please try again";
				}
				$logger->log(Logger::ERR, "unable to assign groups: " . $e->getMessage());
				$logger->log(Logger::ERR, $e->getTraceAsString());
			}
		}
		
		if(!empty($error)) {
			$this->getResponse()->setStatusCode(500);
			return new JsonModel(array(
				'error' => $error
			));
		}
		
		return new JsonModel(array(
			'id' => $id
		));
	}
	
	public function rolesUnassignGroupsAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$error = "";
		$id = $this->params()->fromRoute('id');
		$security_group_role_links_mdl = SecurityGroupRoleLinks::factory($sm);
		
		if($this->getRequest()->isPost()) {
			$group_id = $this->params()->fromPost('group_id');
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
				$security_group_role_links_mdl->delete(array(
					'group_id' => $group_id,
					'role_id' => $id
				));
				
				$con->commit();
			} catch(\Exception $e) {
				$con->rollback();
				
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to complete unassigning groups, please try again";
				}
				$logger->log(Logger::ERR, "unable to unassign groups: " . $e->getMessage());
				$logger->log(Logger::ERR, $e->getTraceAsString());
			}
		}
		
		if(!empty($error)) {
			$this->getResponse()->setStatusCode(500);
			return new JsonModel(array(
				'error' => $error
			));
		}
		
		return new JsonModel(array(
			'id' => $id
		));
	}
	
	public function rolesAssignExcludeRolesAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$error = "";
		$id = $this->params()->fromRoute('id');
		$security_role_exclude_roles_mdl = SecurityRoleExcludeRoles::factory($sm);
		
		if($this->getRequest()->isPost()) {
			$roles = $this->params()->fromPost('roles');
			$roles = split(',', $roles);
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
				if(count($roles) > 0) {
					foreach($roles as $role_id) {
						if(!$security_role_exclude_roles_mdl->exists(array(
							'role_id' => $id,
							'exclude_role_id' => $role_id
						))) {
							$security_role_exclude_roles_mdl->insert($id, $role_id);
						}
					}
				}
				
				$con->commit();
			} catch(\Exception $e) {
				$con->rollback();
				
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to complete assigning excluded roles, please try again";
				}
				$logger->log(Logger::ERR, "unable to assign excluded roles : " . $e->getMessage());
				$logger->log(Logger::ERR, $e->getTraceAsString());
			}
		}
		
		if(!empty($error)) {
			$this->getResponse()->setStatusCode(500);
			return new JsonModel(array(
				'error' => $error
			));
		}
		
		return new JsonModel(array(
			'id' => $id
		));
	}
	
	public function rolesUnassignExcludeRolesAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$error = "";
		$id = $this->params()->fromRoute('id');
		$security_role_exclude_roles_mdl = SecurityRoleExcludeRoles::factory($sm);
		
		if($this->getRequest()->isPost()) {
			$role_id = $this->params()->fromPost('role_id');
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
				$security_role_exclude_roles_mdl->delete(array(
					'role_id' => $id,
					'exclude_role_id' => $role_id
				));
				
				$con->commit();
			} catch(\Exception $e) {
				$con->rollback();
				
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to complete unassigning excluded roles, please try again";
				}
				$logger->log(Logger::ERR, "unable to unassign excluded roles : " . $e->getMessage());
				$logger->log(Logger::ERR, $e->getTraceAsString());
			}
		}
		
		if(!empty($error)) {
			$this->getResponse()->setStatusCode(500);
			return new JsonModel(array(
				'error' => $error
			));
		}
		
		return new JsonModel(array(
			'id' => $id
		));
	}
	
	public function permissionsAction() {
		return new ViewModel();
	}
	
	public function permissionsAddAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$form = $this->buildPermissionAddForm();
		$error = "";
		
		if($this->getRequest()->isPost()) {
			$form->setData($this->params()->fromPost());
			
			if($form->isValid()) {
				$security_permissions_mdl = SecurityPermissions::factory($sm);
				$con = $db->getDriver()->getConnection();
				$post = $form->getData();
				
				try {
					$con->beginTransaction();
					
					// check if this name is used
					$role = $security_permissions_mdl->get(array(
						'name' => $post['name']
					));
					if($role !== false) {
						$error = "Permission name already exists";
						throw new \Exception($error);
					}
					
					$id = $security_permissions_mdl->insert($post['name'], $post['description']);
					
					$con->commit();
				} catch(\Exception $e) {
					$con->rollback();
						
					$p = $e->getPrevious();
					if($p) {
						$e = $p;
					}
					
					if(empty($error)) {
						$error = "Unable to complete adding permission, please try again";
					}
					$logger->log(Logger::ERR, "unable to add permission: " . $e->getMessage());
					$logger->log(Logger::ERR, $e->getTraceAsString());
				}
			} else {
				$messages = $form->getMessages();
				foreach($messages as $field => $msg) {
					$error = array_shift(array_values($msg));
					break;
				}
			}
		}
		
		if(!empty($error)) {
			$this->getResponse()->setStatusCode(500);
			return new JsonModel(array(
				'error' => $error
			));
		}
		
		return new JsonModel(array(
			'id' => $id
		));
	}
	
	public function permissionsEditAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$form = $this->buildRoleEditForm();
		$error = "";
		$success = "";
		$post = $this->params()->fromPost();
		$id = $this->params()->fromRoute('id');
		
		$security_permissions_mdl = SecurityPermissions::factory($sm);
		$permission = $security_permissions_mdl->get($id);
		
		$view = new ViewModel(array(
			'id' => $id
		));
		
		if($permission === false) {
			$view->setTemplate('security/invalid-permission');
		} else if($this->getRequest()->isPost()) {
			$form->setData($this->params()->fromPost());
			
			if($form->isValid()) {
				$con = $db->getDriver()->getConnection();
				$post = $form->getData();
				
				try {
					$con->beginTransaction();
					
					// check if this name is used
					$ex_permission = $security_permissions_mdl->getadv(array(
						'name' => $post['name'],
						'!id' => $id
					));
					if($ex_permission !== false) {
						$error = "Permission name already exists";
						throw new \Exception($error);
					}
					
					$security_permissions_mdl->update($id, array(
						'name' => $post['name'],
						'description' => $post['description']
					));
					
					$con->commit();
					
					$success = "Successfully updated permission";
				} catch(\Exception $e) {
					$con->rollback();
						
					$p = $e->getPrevious();
					if($p) {
						$e = $p;
					}
					
					if(empty($error)) {
						$error = "Unable to complete updating permission, please try again";
					}
					$logger->log(Logger::ERR, "unable to update permission: " . $e->getMessage());
					$logger->log(Logger::ERR, $e->getTraceAsString());
				}
			}
		} else {
			$post = $permission->getArrayCopy();
		}
		
		$view->setVariables(array(
			'form' => $form,
			'error' => $error,
			'success' => $success,
			'post' => $post,
			'permission' => ($permission !== false) ? $permission->getArrayCopy() : array()
		));
		
		return $view;
	}
	
	public function permissionsDeleteAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$error = "";
		$id = $this->params()->fromRoute('id');
		
		$security_permissions_mdl = SecurityPermissions::factory($sm);
		
		if($this->getRequest()->isPost()) {			
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
				$security_permissions_mdl->delete($id);
				
				$con->commit();
			} catch(\Exception $e) {
				$con->rollback();
					
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to complete deleting permission, please try again";
				}
				$logger->log(Logger::ERR, "unable to delete permission: " . $e->getMessage());
				$logger->log(Logger::ERR, $e->getTraceAsString());
			}
		}
		
		if(!empty($error)) {
			$this->getResponse()->setStatusCode(500);
			return new JsonModel(array(
				'error' => $error
			));
		}
		
		return new JsonModel(array(
			'id' => $id
		));
	}
	
	public function permissionsAssignResourcesAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$error = "";
		$id = $this->params()->fromRoute('id');
		$security_resource_permission_links_mdl = SecurityResourcePermissionLinks::factory($sm);
		
		if($this->getRequest()->isPost()) {
			$resources = $this->params()->fromPost('resources');
			$resources = split(',', $resources);
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
				if(count($resources) > 0) {
					foreach($resources as $resource_id) {
						if(!$security_resource_permission_links_mdl->exists(array(
							'resource_id' => $resource_id,
							'permission_id' => $id
						))) {
							$security_resource_permission_links_mdl->insert($resource_id, $id);
						}
					}
				}
				
				$con->commit();
			} catch(\Exception $e) {
				$con->rollback();
				
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to complete assigning resources, please try again";
				}
				$logger->log(Logger::ERR, "unable to assign resources: " . $e->getMessage());
				$logger->log(Logger::ERR, $e->getTraceAsString());
			}
		}
		
		if(!empty($error)) {
			$this->getResponse()->setStatusCode(500);
			return new JsonModel(array(
				'error' => $error
			));
		}
		
		return new JsonModel(array(
			'id' => $id
		));
	}
	
	public function permissionsUnassignResourcesAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$error = "";
		$id = $this->params()->fromRoute('id');
		$security_resource_permission_links_mdl = SecurityResourcePermissionLinks::factory($sm);
		
		if($this->getRequest()->isPost()) {
			$resource_id = $this->params()->fromPost('resource_id');
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
				$security_resource_permission_links_mdl->delete(array(
					'permission_id' => $id,
					'resource_id' => $resource_id
				));
				
				$con->commit();
			} catch(\Exception $e) {
				$con->rollback();
				
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to complete unassigning resources, please try again";
				}
				$logger->log(Logger::ERR, "unable to unassign resources: " . $e->getMessage());
				$logger->log(Logger::ERR, $e->getTraceAsString());
			}
		}
		
		if(!empty($error)) {
			$this->getResponse()->setStatusCode(500);
			return new JsonModel(array(
				'error' => $error
			));
		}
		
		return new JsonModel(array(
			'id' => $id
		));
	}
	
	public function permissionsAssignRolesAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$error = "";
		$id = $this->params()->fromRoute('id');
		$security_role_permission_links_mdl = SecurityRolePermissionLinks::factory($sm);
		
		if($this->getRequest()->isPost()) {
			$roles = $this->params()->fromPost('roles');
			$roles = split(',', $roles);
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
				if(count($roles) > 0) {
					foreach($roles as $role_id) {
						if(!$security_role_permission_links_mdl->exists(array(
							'role_id' => $role_id,
							'permission_id' => $id
						))) {
							$security_role_permission_links_mdl->insert($role_id, $id);
						}
					}
				}
				
				$con->commit();
			} catch(\Exception $e) {
				$con->rollback();
				
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to complete assigning roles, please try again";
				}
				$logger->log(Logger::ERR, "unable to assign roles: " . $e->getMessage());
				$logger->log(Logger::ERR, $e->getTraceAsString());
			}
		}
		
		if(!empty($error)) {
			$this->getResponse()->setStatusCode(500);
			return new JsonModel(array(
				'error' => $error
			));
		}
		
		return new JsonModel(array(
			'id' => $id
		));
	}
	
	public function permissionsUnassignRolesAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$error = "";
		$id = $this->params()->fromRoute('id');
		$security_role_permission_links_mdl = SecurityRolePermissionLinks::factory($sm);
		
		if($this->getRequest()->isPost()) {
			$role_id = $this->params()->fromPost('role_id');
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
				$security_role_permission_links_mdl->delete(array(
					'permission_id' => $id,
					'role_id' => $role_id
				));
				
				$con->commit();
			} catch(\Exception $e) {
				$con->rollback();
				
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to complete unassigning roles, please try again";
				}
				$logger->log(Logger::ERR, "unable to unassign roles: " . $e->getMessage());
				$logger->log(Logger::ERR, $e->getTraceAsString());
			}
		}
		
		if(!empty($error)) {
			$this->getResponse()->setStatusCode(500);
			return new JsonModel(array(
				'error' => $error
			));
		}
		
		return new JsonModel(array(
			'id' => $id
		));
	}
	
	public function resourcesAction() {
		return new ViewModel();
	}
	
	public function resourcesAddAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$form = $this->buildResourceAddForm();
		$error = "";
		
		if($this->getRequest()->isPost()) {
			$form->setData($this->params()->fromPost());
			
			if($form->isValid()) {
				$security_resources_mdl = SecurityResources::factory($sm);
				$con = $db->getDriver()->getConnection();
				$post = $form->getData();
				
				try {
					$con->beginTransaction();
					
					// check if this controller/action is used
					$resource = $security_resources_mdl->get(array(
						'controller' => $post['controller'],
						'action' => $post['action']
					));
					if($resource !== false) {
						$error = "Controller/action already exists";
						throw new \Exception($error);
					}
					
					$id = $security_resources_mdl->insert($post['controller'], $post['action']);
					
					$con->commit();
				} catch(\Exception $e) {
					$con->rollback();
						
					$p = $e->getPrevious();
					if($p) {
						$e = $p;
					}
					
					if(empty($error)) {
						$error = "Unable to complete adding resource, please try again";
					}
					$logger->log(Logger::ERR, "unable to add resource: " . $e->getMessage());
					$logger->log(Logger::ERR, $e->getTraceAsString());
				}
			} else {
				$messages = $form->getMessages();
				foreach($messages as $field => $msg) {
					$error = array_shift(array_values($msg));
					break;
				}
			}
		}
		
		if(!empty($error)) {
			$this->getResponse()->setStatusCode(500);
			return new JsonModel(array(
				'error' => $error
			));
		}
		
		return new JsonModel(array(
			'id' => $id
		));
	}
	
	public function resourcesEditAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$form = $this->buildResourceEditForm();
		$error = "";
		$success = "";
		$post = $this->params()->fromPost();
		$id = $this->params()->fromRoute('id');
		
		$security_resources_mdl = SecurityResources::factory($sm);
		$resource = $security_resources_mdl->get($id);
		
		$view = new ViewModel(array(
			'id' => $id
		));
		
		if($resource === false) {
			$view->setTemplate('security/invalid-resource');
		} else if($this->getRequest()->isPost()) {
			$form->setData($this->params()->fromPost());
			
			if($form->isValid()) {
				$con = $db->getDriver()->getConnection();
				$post = $form->getData();
				
				try {
					$con->beginTransaction();
					
					// check if this name is used
					$ex_resource = $security_resources_mdl->getadv(array(
						'controller' => $post['controller'],
						'action' => $post['action'],
						'!id' => $id
					));
					if($ex_resource !== false) {
						$error = "Controller/action already exists";
						throw new \Exception($error);
					}
					
					$security_resources_mdl->update($id, array(
						'controller' => $post['controller'],
						'action' => $post['action']
					));
					
					$con->commit();
					
					$success = "Successfully updated resource";
				} catch(\Exception $e) {
					$con->rollback();
						
					$p = $e->getPrevious();
					if($p) {
						$e = $p;
					}
					
					if(empty($error)) {
						$error = "Unable to complete updating resource, please try again";
					}
					$logger->log(Logger::ERR, "unable to update resource: " . $e->getMessage());
					$logger->log(Logger::ERR, $e->getTraceAsString());
				}
			}
		} else {
			$post = $resource->getArrayCopy();
		}
		
		$view->setVariables(array(
			'form' => $form,
			'error' => $error,
			'success' => $success,
			'post' => $post,
			'resource' => ($resource !== false) ? $resource->getArrayCopy() : array()
		));
		
		return $view;
	}
	
	public function resourcesDeleteAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$error = "";
		$id = $this->params()->fromRoute('id');
		
		$security_resources_mdl = SecurityResources::factory($sm);
		
		if($this->getRequest()->isPost()) {			
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
				$security_resources_mdl->delete($id);
				
				$con->commit();
			} catch(\Exception $e) {
				$con->rollback();
					
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to complete deleting resource, please try again";
				}
				$logger->log(Logger::ERR, "unable to delete resource: " . $e->getMessage());
				$logger->log(Logger::ERR, $e->getTraceAsString());
			}
		}
		
		if(!empty($error)) {
			$this->getResponse()->setStatusCode(500);
			return new JsonModel(array(
				'error' => $error
			));
		}
		
		return new JsonModel(array(
			'id' => $id
		));
	}
	
	public function resourcesAssignPermissionsAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$error = "";
		$id = $this->params()->fromRoute('id');
		$security_resource_permission_links_mdl = SecurityResourcePermissionLinks::factory($sm);
		
		if($this->getRequest()->isPost()) {
			$permissions = $this->params()->fromPost('permissions');
			$permissions = split(',', $permissions);
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
				if(count($permissions) > 0) {
					foreach($permissions as $permission_id) {
						if(!$security_resource_permission_links_mdl->exists(array(
							'resource_id' => $id,
							'permission_id' => $permission_id
						))) {
							$security_resource_permission_links_mdl->insert($id, $permission_id);
						}
					}
				}
				
				$con->commit();
			} catch(\Exception $e) {
				$con->rollback();
				
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to complete assigning permissions, please try again";
				}
				$logger->log(Logger::ERR, "unable to assign permissions: " . $e->getMessage());
				$logger->log(Logger::ERR, $e->getTraceAsString());
			}
		}
		
		if(!empty($error)) {
			$this->getResponse()->setStatusCode(500);
			return new JsonModel(array(
				'error' => $error
			));
		}
		
		return new JsonModel(array(
			'id' => $id
		));
	}
	
	public function resourcesUnassignPermissionsAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$error = "";
		$id = $this->params()->fromRoute('id');
		$security_resource_permission_links_mdl = SecurityResourcePermissionLinks::factory($sm);
		
		if($this->getRequest()->isPost()) {
			$permission_id = $this->params()->fromPost('permission_id');
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
				$security_resource_permission_links_mdl->delete(array(
					'permission_id' => $permission_id,
					'resource_id' => $id
				));
				
				$con->commit();
			} catch(\Exception $e) {
				$con->rollback();
				
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to complete unassigning permissions, please try again";
				}
				$logger->log(Logger::ERR, "unable to unassign permissions: " . $e->getMessage());
				$logger->log(Logger::ERR, $e->getTraceAsString());
			}
		}
		
		if(!empty($error)) {
			$this->getResponse()->setStatusCode(500);
			return new JsonModel(array(
				'error' => $error
			));
		}
		
		return new JsonModel(array(
			'id' => $id
		));
	}
	
	public function usersGroupsAction() {
		return new ViewModel(array());
	}
}
?>