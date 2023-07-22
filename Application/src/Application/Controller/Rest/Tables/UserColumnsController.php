<?php
namespace Application\Controller\Rest\Tables;

use Els\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;

use Application\Model\TableColumns;

class UserColumnsController extends AbstractRestfulController {
	public function getList() {
		$table_columns_mdl = TableColumns::factory($this->getServiceLocator());
		
		$filter = $this->getFilter();
		$filter['user_id'] = $this->authentication()->getAuthenticatedUserId();
		$filter['table_id'] = $this->params()->fromRoute('table_id');
		
		$items = $table_columns_mdl->filterUser($filter, $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$table_columns_mdl = TableColumns::factory($this->getServiceLocator());
		$item = $table_columns_mdl->get(array(
			'user_id' => $this->authentication()->getAuthenticatedUserId(),
			'table_id' => $this->params()->fromRoute('table_id'),
			'id' => $id
		));
		
		return $item;
	}
}
?>