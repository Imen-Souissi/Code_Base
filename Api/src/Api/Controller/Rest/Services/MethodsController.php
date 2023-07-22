<?php
namespace Api\Controller\Rest\Services;

use Els\Mvc\Controller\AbstractRestfulController;
use Api\Model\ApiServiceMethods;

class MethodsController extends AbstractRestfulController {
	public function getList() {
		$api_service_methods_mdl = ApiServiceMethods::factory($this->getServiceLocator());
        $filter = $this->getFilter();
        $filter['service_id'] = $this->params()->fromRoute('service_id');
        
		$items = $api_service_methods_mdl->filter($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$api_service_methods_mdl = ApiServiceMethods::factory($this->getServiceLocator());
		$item = $api_service_methods_mdl->get(array(
            'id' => $id,
            'service_id' => $this->params()->fromRoute('service_id')
        ));
		return $item;
	}
}
?>