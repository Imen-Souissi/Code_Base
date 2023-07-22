<?php
namespace Application\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\TableIdentifier;

use Els\Mvc\Model\DbModel;
use Els\Mvc\Model\DbModelFactoryInterface;

use Zend\ServiceManager\ServiceLocatorInterface;

class Cities extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = 'id';
	
	public function __construct(Adapter $dbh, $database = 'app') {
		parent::__construct($dbh, new TableIdentifier('cities', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['app']);
	}
	
	public function insert($country_id, $region_code, $name) {
		return $this->_insert(array(
			'country_id' => $country_id,
			'region_code' => $region_code,
			'name' => $name,
			'ctime' => new Expression("NOW()")
		));
	}
	
	public function filter($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database) {
			$select->join(array('C' => new TableIdentifier('countries', $database)),
						  "{$table}.country_id = C.id",
						  array(),
						  Select::JOIN_INNER);
			$select->join(array('S' => new TableIdentifier('states', $database)),
						  "{$table}.region_code = S.code",
						  array(),
						  Select::JOIN_LEFT);
			
			$columns = array(
				'country' => 'C.name',
				'state' => 'S.name'
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array("name ASC"), $columns)
				 ->applyRange($select, $range);
				 
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
}
?>