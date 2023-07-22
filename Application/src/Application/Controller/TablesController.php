<?php
namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Log\Logger;
use Zend\Json\Json;
use Zend\Db\Sql\Expression;

use Application\Model\Tables;
use Application\Model\TableColumns;
use Application\Model\TableUserColumns;

class TablesController extends AbstractActionController {
	public function editUserColumnsAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$tables_mdl = Tables::factory($sm);
		$table_columns_mdl = TableColumns::factory($sm);
		$table_user_columns_mdl = TableUserColumns::factory($sm);
		
		$id = $this->params()->fromRoute('id');
		$error = "";
		
		if($this->getRequest()->isPost()) {
			$user_id = $this->authentication()->getAuthenticatedUserId();			
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
				$data = Json::decode($this->getRequest()->getContent(), Json::TYPE_ARRAY);
				$null = new Expression('NULL');
				
				foreach($data as $column) {
					if(empty($column['id'])) {
						continue;
					}
					
					// check if this user has this column defined
					$row = $table_user_columns_mdl->get(array(
						'user_id' => $user_id,
						'column_id' => $column['id']
					));
					
					$display = (empty($column['display'])) ? $null : $column['display'];
					$sort = (empty($column['sort'])) ? 0 : $column['sort'];
					$show = (empty($column['show'])) ? $null : $column['show'];
					
					if($row === false) {
						// insert this user column
						$table_user_columns_mdl->insert(
							$column['id'],
							$user_id,
							$display,
							$sort,
							$show
						);
					} else {
						// update this user column
						$table_user_columns_mdl->update($row->id, array(
							'display' => $display,
							'sort' => $sort,
							'show' => $show
						));
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
					$error = "Unable to save column configurations";
				}
				$logger->log(Logger::ERR, "unable to save column configurations : " . $e->getMessage());
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
	
	public function resetUserColumnsAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$tables_mdl = Tables::factory($sm);
		$table_columns_mdl = TableColumns::factory($sm);
		$table_user_columns_mdl = TableUserColumns::factory($sm);
		
		$id = $this->params()->fromRoute('id');
		$error = "";
		
		if($this->getRequest()->isPost()) {
			$user_id = $this->authentication()->getAuthenticatedUserId();
			$post = $this->params()->fromPost();
			$con = $db->getDriver()->getConnection();			
			
			try {
				$con->beginTransaction();
				
				$columns = $table_columns_mdl->filter(array(
					'table_id' => $post['table_id']
				), array(), array(), $total);
				
				foreach($columns as $column) {
					// delete this column
					$table_user_columns_mdl->delete(array(
						'user_id' => $user_id,
						'column_id' => $column->id
					));					
				}
				
				$con->commit();
			} catch(\Exception $e) {
				$con->rollback();
				
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to reset column configurations";
				}
				$logger->log(Logger::ERR, "unable to reset column configurations : " . $e->getMessage());
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
}

?>