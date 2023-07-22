<?php
namespace Contract\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\TableIdentifier;

use Els\Mvc\Model\DbModel;
use Els\Mvc\Model\DbModelFactoryInterface;

use Zend\ServiceManager\ServiceLocatorInterface;

class NotificationSchedules extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = 'id';
	
	public function __construct(Adapter $dbh, $database = 'contract') {
		parent::__construct($dbh, new TableIdentifier('notification_schedules', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['contract']);
	}

	public function insert($data) {
		return $this->_insert(array(
			'name' => $data['name'],
			'active' => $data['active']
		));
	}
	
	public function get($identifiers) {
		$identifiers = $this->normalizeIdentifiers($identifiers);
		$result = $this->filter($identifiers, array(), array(), $total);
		return $result->current();
	}
	
	public function getDaysStr($identifiers) {
		$identifiers = $this->normalizeIdentifiers($identifiers);
		$result = $this->filterDaysStr($identifiers, array(), array(), $total);
		return $result->current();
	}
	
	public function filter($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database) {
			$columns = array(
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array("{$table}.id ASC"), $columns)
				 ->applyRange($select, $range);
				 
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}

	public function filterDaysStr($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
	
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database) {
			$select->join(array('NO' => new TableIdentifier('notification_options', $database)),
					"{$table}.id = NO.notification_schedule_id",
					array(
					),
					Select::JOIN_LEFT);
			
			$select->group("{$table}.id");
			
			$columns = array(
				'id',
				'name',
				'active',
				'days' => new Expression("GROUP_CONCAT(`NO`.days_out SEPARATOR ',')")
			);
			$select->columns($columns);
				
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
			->applySort($select, $sort, array("{$table}.id ASC"), $columns)
			->applyRange($select, $range);
				
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
	
		$total = $this->getCalcFoundRows($result, $range);

		return $result;
	}
}
?>