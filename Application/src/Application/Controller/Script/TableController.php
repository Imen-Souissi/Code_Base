<?php
namespace Application\Controller\Script;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ConsoleModel;
use Zend\Log\Logger;
use Zend\Json\Json;

use Application\Model\Tables;
use Application\Model\TableColumns;
use Application\Model\TableUserColumns;

class TableController extends AbstractActionController {
    public function buildAction() {
        $sm = $this->getServiceLocator();
        $config = $sm->get('Config');
		$logger = $sm->get('console_logger');
		
        $tables_mdl = Tables::factory($sm);
        $table_columns_mdl = TableColumns::factory($sm);
        
		$result = new ConsoleModel();		
        
		$logger->log(Logger::INFO, "preparing to generate tables");
        
		$modules = array();
		
		// load the table configurations
		if(!empty($config['table']) && is_array($config['table'])) {
			foreach($config['table'] as $table_cfg_file) {
				if(file_exists($table_cfg_file)) {
					$table_cfg = include_once($table_cfg_file);
					if(!empty($table_cfg)) {
						$modules = array_merge_recursive($modules, $table_cfg);
					}
				}
			}
		}
		
        if(!empty($modules) && is_array($modules)) {
            foreach($modules as $module => $tables) {
				foreach($tables as $table) {
					if(empty($table['name'])) {
						continue;
					}
					
					// check if this table exists
					$row = $tables_mdl->get(array(
						'module' => $module,
						'name' => $table['name']
					));
					
					if($row === false) {
						$table_id = $tables_mdl->insert(
							$module,
							$table['name'],
							$table['description']
						);
					} else {
						$table_id = $row->id;
						$tables_mdl->update($table_id, array(
							'description' => $table['description']
						));
					}
					
					// deactivate all columns to begin with
					$table_columns_mdl->update(array(
						'table_id' => $table_id
					), array(
						'active' => 0
					));
					
					// check if these table columns exists
					if(!empty($table['columns']) && is_array($table['columns'])) {
						foreach($table['columns'] as $sort => $column) {
							$row = $table_columns_mdl->get(array(
								'table_id' => $table_id,
								'field' => $column['field']
							));
							
							$show = (empty($column['show'])) ? 'DYNAMIC' : $column['show'];
							
							if($row === false) {
								$column_id = $table_columns_mdl->insert(
									$table_id,
									$column['field'],
									$column['display'],
									$sort,
									$show,
									1
								);
							} else {
								$column_id = $row->id;
								$table_columns_mdl->update($column_id, array(
									'display' => $column['display'],
									'sort' => $sort,
									'show' => $show,
									'active' => 1
								));
							}
						}
					}
				}
            }
        }
        
        $logger->log(Logger::INFO, "successfully generated tables");
        return $result;
    }
}
?>