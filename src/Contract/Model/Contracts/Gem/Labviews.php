<?php
namespace Contract\Model\Contracts\Gem;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\TableIdentifier;
use Zend\ServiceManager\ServiceLocatorInterface;

use Gem\Model\Labviews as GemLabviews;

class Labviews extends GemLabviews {
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['gem'], $databases['hr']);
	}
	
	public function getContract($identifiers) {
		$identifiers = $this->normalizeIdentifiers($identifiers);
		$result = $this->filterLabview($identifiers, array(), array(), $total);
		return $result->current();
	}
	
	public function filterContract($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		$hr_database = $this->hr_database;
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database, $hr_database) {
			$select->join(array('U' => new TableIdentifier('hr_users', $hr_database)),
				    "{$table}.creator_id = U.id",
				    array(
						'creator' => 'display_name',
						'creator_department_id' => 'department_id',
						'creator_department' => 'department'
				    ),
				    Select::JOIN_LEFT);
			$select->join(array('LVC' => new TableIdentifier('labview_contracts', $database)),
					"{$table}.id = LVC.labview_id",
					array(),
					Select::JOIN_LEFT);
			
			$columns = array(
				'creator' => 'U.display_name',
				'creator_department_id' => 'U.department_id',
				'creator_department' => 'U.department',
				'contract_id' => 'LVC.contract_id'
			);
			
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array("{$table}.name ASC"), $columns)
				 ->applyRange($select, $range);
			$select->group(array("{$table}.id"));			
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
	
	public function getContractAvailable($identifiers) {
		$identifiers = $this->normalizeIdentifiers($identifiers);
		$result = $this->filterContractAvailable($identifiers, array(), array(), $total);
		return $result->current();
	}
	
	public function filterContractAvailable($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		$hr_database = $this->hr_database;
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database, $hr_database) {
			$select->columns(array(
				'id',
				'name',
				'description',
				'unavailable' => new Expression("MAX(IF(LVC.contract_id = ?, 1, 0))", array($filter['contract_id'])),
			));
				
			$having = array();
			if($filter['contract_id']) {
				$having['unavailable'] = 0;
			}
			unset($filter['contract_id']);
			
			$select->join(array('U' => new TableIdentifier('hr_users', $hr_database)),
				    "{$table}.creator_id = U.id",
				    array(
						'creator' => 'display_name',
						'creator_department_id' => 'department_id',
						'creator_department' => 'department'
				    ),
				    Select::JOIN_LEFT);
			$select->join(array('LVC' => new TableIdentifier('labview_contracts', $database)),
					"{$table}.id = LVC.labview_id",
					array(),
					Select::JOIN_LEFT);
			
			$columns = array(
				'creator' => 'U.display_name',
				'creator_department_id' => 'U.department_id',
				'creator_department' => 'U.department',
				'contract_id' => 'LVC.contract_id'
			);
			
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array("{$table}.name ASC"), $columns)
				 ->applyRange($select, $range);
			$select->group(array("{$table}.id"));
			$select->having($having);
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
}
?>