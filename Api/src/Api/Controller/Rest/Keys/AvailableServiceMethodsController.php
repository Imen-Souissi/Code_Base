<?php
namespace Api\Controller\Rest\Keys;

use Els\Mvc\Controller\AbstractRestfulController;
use Api\Model\ApiServiceMethods;

class AvailableServiceMethodsController extends AbstractRestfulController {
	public function getList() {
		$api_service_methods_mdl = ApiServiceMethods::factory($this->getServiceLocator());
        $filter = $this->getFilter();
        $filter['key_id'] = $this->params()->fromRoute('key_id');
        
		$items = $api_service_methods_mdl->filterKeyAvailable($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$api_service_methods_mdl = ApiServiceMethods::factory($this->getServiceLocator());
		$item = $api_service_methods_mdl->getKeyAvailable(array(
            'id' => $id,
            'key_id' => $this->params()->fromRoute('key_id')
        ));
		return $item;
	}
}
?>