<?php
namespace Api\Controller\Rest\Keys;

use Els\Mvc\Controller\AbstractRestfulController;
use Api\Model\ApiKeyServiceMethods;

class ServiceMethodsController extends AbstractRestfulController {
	public function getList() {
		$api_key_service_methods_mdl = ApiKeyServiceMethods::factory($this->getServiceLocator());
        $filter = $this->getFilter();
        $filter['key_id'] = $this->params()->fromRoute('key_id');
        
		$items = $api_key_service_methods_mdl->filter($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$api_key_service_methods_mdl = ApiKeyServiceMethods::factory($this->getServiceLocator());
		$item = $api_key_service_methods_mdl->get(array(
            'id' => $id,
            'key_id' => $this->params()->fromRoute('key_id')
        ));
		return $item;
	}
}
?>