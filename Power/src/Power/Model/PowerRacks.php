<?php
namespace Power\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\TableIdentifier;

use Els\Mvc\Model\DbModel;
use Els\Mvc\Model\DbModelFactoryInterface;

use Zend\ServiceManager\ServiceLocatorInterface;

class PowerRacks extends DbModel {
	protected $primary_key = 'id';
	
	public function __construct(Adapter $dbh, $database = 'power') {
		parent::__construct($dbh, new TableIdentifier('power_racks', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['power']);
	}
	
	public function insert(
        $power_iq_rack_id,
        $rack_id,
		$name,
        $power_iq_aisle_id,
		$power_iq_room_id
    ) {
		return $this->_insert(array(
            'power_iq_rack_id' => $power_iq_rack_id,
            'rack_id' => $rack_id,
			'name' => $name,
            'power_iq_aisle_id' => $power_iq_aisle_id,
			'power_iq_room_id' => $power_iq_room_id,
			'ctime' => new Expression('NOW()'),
			'etime' => new Expression('NOW()')
		));
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
	
	public function getDatacenter($identifiers) {
		$identifiers = $this->normalizeIdentifiers($identifiers);
		$result = $this->filterDatacenter($identifiers);
		return $result->current();
	}
	
	public function filterDatacenter($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database) {
			$select->join(array('A' => new TableIdentifier('power_aisles', $database)),
						  "{$table}.power_iq_aisle_id = A.power_iq_aisle_id",
						  array(
								'aisle' => new Expression('MAX(A.`name`)')
						  ),
						  Select::JOIN_LEFT);			
			$select->join(array('R' => new TableIdentifier('power_rooms', $database)),
						  "{$table}.power_iq_room_id = R.power_iq_room_id",
						  array(
								'room' => new Expression('MAX(R.`name`)')
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('F' => new TableIdentifier('power_floors', $database)),
						  "A.power_iq_floor_id = F.power_iq_floor_id OR R.power_iq_floor_id = F.power_iq_floor_id",
						  array(
								'floor' => new Expression('MAX(F.`name`)')
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('D' => new TableIdentifier('power_datacenters', $database)),
						  "F.power_iq_datacenter_id = D.power_iq_datacenter_id OR R.power_iq_datacenter_id = D.power_iq_datacenter_id",
						  array(
								'datacenter' => new Expression('MAX(D.`name`)')
						  ),
						  Select::JOIN_LEFT);
			
			$columns = array(
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array("{$table}.id ASC"), $columns)
				 ->applyRange($select, $range);
			$select->group(array("{$table}.id"));
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
}
?>