<?php
namespace Brics\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\TableIdentifier;

use Els\Mvc\Model\DbModel;
use Els\Mvc\Model\DbModelFactoryInterface;

use Zend\ServiceManager\ServiceLocatorInterface;

class Devices extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = 'id';
	
	public function __construct(Adapter $dbh, $database = 'brics') {
		parent::__construct($dbh, new TableIdentifier('device', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'bdb', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['brics']);
	}
	
	public function get($identifiers) {
		$identifiers = $this->normalizeIdentifiers($identifiers);
		$result = $this->filter($identifiers, array(), array(), $total);		
		return $result->current();
	}
	
	public function filter($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database) {
			$select->join(array('L' => new TableIdentifier('labs', $database)),
						  "{$table}.labid = L.id",
						  array(
								'lab' => 'name',
								'location' => 'location',
								'owner' => 'owner'
						  ),
						  Select::JOIN_LEFT);
			
			$columns = array(
				'lab' => 'L.name',
				'location' => 'L.location',
				'owner' => 'L.owner'
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array("{$table}.id ASC"), $columns)
				 ->applyRange($select, $range);
			$select->group(array("{$table}.id"));
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
			//exit;
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
	
	 public function filterRack($filter, $range, $sort, &$total) {
        $self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database) {
			$select->columns(array(
				'id',
				'building',
				'rack'
			));
			
			$select->join(array('L' => new TableIdentifier('labs', $database)),
						  "{$table}.labid = L.id",
						  array(
								'lab' => 'name',
								'location' => 'location',
								'owner' => 'owner'
						  ),
						  Select::JOIN_LEFT);
			
			$columns = array(
				'lab' => 'L.name',
				'location' => 'L.location',
				'owner' => 'L.owner'
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array("{$table}.id ASC"), $columns)
				 ->applyRange($select, $range);
			$select->group(array("{$table}.rack"));
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
			//exit;
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
    }
}
?>