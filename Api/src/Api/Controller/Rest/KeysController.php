<?php
namespace Api\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Api\Model\ApiKeys;

class KeysController extends AbstractRestfulController {
	public function getList() {
		$sm = $this->getServiceLocator();
		$config = $sm->get('Config');
		
		$app = (empty($config['api']['app'])) ? 'app' : $config['api']['app'];
		
		$api_keys_mdl = ApiKeys::factory($sm);
		$filter = $this->getFilter();
		$filter['app'] = $app;
		
		if(!$this->authorization()->isPermitted('Api::Keys', 'access-all')) {
			$filter['user_id'] = $this->authentication()->getAuthenticatedUserId();
		}
		
		$items = $api_keys_mdl->filter($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$sm = $this->getServiceLocator();
		$config = $sm->get('Config');
		
		$app = (empty($config['api']['app'])) ? 'app' : $config['api']['app'];		
		
		$api_keys_mdl = ApiKeys::factory($sm);
		return $api_keys_mdl->get(array(
			'id' => $id,
			'app' => $app
		));
	}
}
?>