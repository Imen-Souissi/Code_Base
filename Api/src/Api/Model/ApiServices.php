<?php
namespace Api\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\TableIdentifier;

use Els\Mvc\Model\DbModel;
use Els\Mvc\Model\DbModelFactoryInterface;

use Zend\ServiceManager\ServiceLocatorInterface;

class ApiServices extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = 'id';
	
	public function __construct(Adapter $dbh, $database = 'api') {
		parent::__construct($dbh, new TableIdentifier('api_services', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['api']);
	}
	
	public function insert($app, $name, $version = 0, $total_methods = 0) {
		return $this->_insert(array(
            'app' => $app,
            'name' => $name,
			'version' => $version,
			'total_methods' => $total_methods,
            'ctime' => new Expression('NOW()'),
			'etime' => new Expression('NOW()')
		));
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
	
	public function computeStats($identifiers) {
		$identifiers = $this->normalizeIdentifiers($identifiers);		
		$cnt = 0;
		
		$result = $this->filterMethodsStats($identifiers, array(), array(), $total);
		foreach($result as $row) {
			$cnt += $this->update($row->id, array(
				'total_methods' => $row->total_methods
			));
		}
		
		return $cnt;
	}
	
	public function filterMethodsStats($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database) {
			$select->columns(array(
				'id',
				'total_methods' => new Expression("COUNT(DISTINCT M.id)")
			));
			$select->join(array('M' => new TableIdentifier('api_service_methods', $database)),
						  "{$table}.id = M.service_id",
						  array(),
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