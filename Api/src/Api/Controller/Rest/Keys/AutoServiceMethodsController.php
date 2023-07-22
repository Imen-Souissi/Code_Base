<?php
namespace Api\Controller\Rest\Keys;

use Els\Mvc\Controller\AbstractRestfulController;
use Api\Model\ApiKeys;
use Api\Model\ApiServiceMethods;

class AutoServiceMethodsController extends AbstractRestfulController {
	public function getList() {
        $sm = $this->getServiceLocator();
        $api_service_methods_mdl = ApiServiceMethods::factory($sm);
        
        $resources = $this->loadResources($this->params()->fromRoute('key_id'));
        
        $filter = $this->getFilter();
        $filter['security_resource_id'] = $resources;
        
		$items = $api_service_methods_mdl->filterAuto($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
        $sm = $this->getServiceLocator();
		$api_service_methods_mdl = ApiServiceMethods::factory($sm);
        
        $resources = $this->loadResources($this->params()->formRoute('key_id'));
        
		$item = $api_service_methods_mdl->getAuto(array(
            'id' => $id,
            'security_resource_id' => $resources
        ));
        
		return $item;
	}
    
    protected function loadResources($key_id) {
        $sm = $this->getServiceLocator();
        $config = $sm->get('Config');
        $app = (empty($config['api']['app'])) ? 'app' : $config['api']['app'];
        
        $api_keys_mdl = ApiKeys::factory($sm);
        
        $allresources = array(0);
        
        $resources = $api_keys_mdl->filterUserSecurityResources(array(
            'id' => $key_id
        ), array(), array(), $total);
        
        foreach($resources as $resource) {
            $allresources[] = $resource->resource_id;
        }
        
        $resources = $api_keys_mdl->filterGroupSecurityResources(array(
           'id' => $key_id
        ), array(), array(), $total);
        
        foreach($resources as $resource) {
            $allresources[] = $resource->role_id;
        }
        
        return array_unique($allresources);
    }
}
?>