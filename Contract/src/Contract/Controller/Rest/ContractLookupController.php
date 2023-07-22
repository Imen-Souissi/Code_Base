<?php
namespace Contract\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;

use Contract\Model\Contracts;

use Hr\Model\HrDepts;
use Hr\Model\HrUsers;

class ContractLookupController extends AbstractRestfulController {
	public function getList() {
		$sm = $this->getServiceLocator();
		
		$contracts_mdl = Contracts::factory($sm);
		
		$filter = $this->getFilter();
		
		//we'll use the same logic from userLoggedIn() to get the perms/labviews for the selected user
		if($filter['user_id']) {
			$user_id = $filter['user_id'];
			$access_all = false;
			
			$security_user_role_links_mdl = \Auth\Model\SecurityUserRoleLinks::factory($sm);
			$security_group_role_links_mdl = \Auth\Model\SecurityGroupRoleLinks::factory($sm);
			$security_resources_mdl = \Auth\Model\SecurityResources::factory($sm);
			
			// we need to load all roles this user has or the roles the group of this user is in has
			$all_roles = array();
			
			$roles = $security_user_role_links_mdl->filter(array(
				'user_id' => $user_id
			), array(), array(), $total);
			
			foreach($roles as $role) {
				$all_roles[$role->role_id] = $role->role_rights_level;
			}
			
			$roles = $security_group_role_links_mdl->filter(array(
				'user_id' => $user_id
			), array(), array(), $total);
			
			foreach($roles as $role) {
				$all_roles[$role->role_id] = $role->role_rights_level;
			}
			
			$all_permitted_resources = array();
			if(count($all_roles) > 0) {
				// check if the roles this user have has access to the required permission for these resources
				$resources = $security_resources_mdl->filterAccess(array(
					'role_id' => array_keys($all_roles)
				), array(), array(), $total);
				foreach($resources as $resource) {
					if($resource->controller == 'Contract::Contracts' && $resource->action == 'access-all') {
						$access_all = true;
						break;
					}
				}
			}
			
			unset($filter['user_id']);
		}
		
		if(!$access_all) {
			$hr_users_mdl = HrUsers::factory($sm);
			$hr_depts_mdl = HrDepts::factory($sm);
			$labviews_mdl = \Gem\Model\Labviews::factory($sm);
			$labview_departments_mdl = \Gem\Model\LabviewDepartments::factory($sm);
				
			// we will need to pull the labview this user is a part of
				
			// let's pull the departments this guy is a in/manages/inherits
			$departments = $hr_depts_mdl->filterUser(array(
					'user_id|supervisor_id|dept_head_id|dept_director_id|dept_vp_id|executive_vp_id|aor_id|ih_user_id|ih2_user_id|ih3_user_id' => $user_id
			), array(), array(), $total);
			
			$department_ids = array(0);
			foreach($departments as $department) {
				$department_ids[] = $department->id;
			}
				
			// let's pull the labviews this guys is a in/manages/inherits
			$labview_ids = array();
			
			$result = $labviews_mdl->filterFull(array(
				'creator_department_id' => $department_ids
			));
			foreach($result as $row) {
				$labview_ids[] = $row->id;
			}
			
			$result = $labviews_mdl->filterLdap(array(
				'user_id' => $user_id
			), array(), array(), $total);
			foreach($result as $row) {
				$labview_ids[] = $row->id;
			}
			
			$result = $labviews_mdl->filterDepartment(array(
				'department_id' => $department_ids
			));
			foreach($result as $row) {
				$labview_ids[] = $row->id;
			}
			
			$labview_ids = array_unique($labview_ids);
			
			if(empty($labview_ids) || count($labview_ids) == 0) {
				$labview_ids = array(0);
			}
			
			$lqs = implode(',', array_fill(0, count($labview_ids), '?'));
			
			$qvals = array_merge($labview_ids, array($user_id, $user_id));
				
			$table = $contracts_mdl->getTableName();
			$filter['access'] = new Expression("MAX(LVC.labview_id IN ({$lqs}) OR {$table}.created_by_id = ? OR CUL.user_id = ?)", $qvals);
		}
		
		$items = $contracts_mdl->filterMore($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
}
?>