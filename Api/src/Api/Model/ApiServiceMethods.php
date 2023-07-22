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

class ApiServiceMethods extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = 'id';
	protected $security_resource_app_database;
	
	public function __construct(Adapter $dbh, $database = 'api', $security_resource_app_database = 'api') {
		$this->security_resource_app_database = $security_resource_app_database;
		parent::__construct($dbh, new TableIdentifier('api_service_methods', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		$security_resource_api = $config['api']['security_resource_app'];
		$security_resource_api = (empty($security_resource_api)) ? 'api' : $security_resource_api;
		
		return new self($dbh, $databases['api'], $databases[$security_resource_api]);
	}
	
	public function insert($service_id, $method, $security_resource_id = null) {
		return $this->_insert(array(
            'service_id' => $service_id,
            'method' => $method,
			'security_resource_id' => $security_resource_id,
            'ctime' => new Expression('NOW()')
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
		$security_resource_app_database = $this->security_resource_app_database;
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database, $security_resource_app_database) {
			$select->join(array('SR' => new TableIdentifier('security_resources', $security_resource_app_database)),
						  "{$table}.security_resource_id = SR.id",
						  array(
								'security_resource_controller' => 'controller',
								'security_resource_action' => 'action'
						  ),
						  Select::JOIN_LEFT);
			
			$columns = array(
				'security_resource_controller' => 'SR.controller',
				'security_resource_action' => 'SR.action',
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
	
	public function filterKeyAvailable($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database) {
			$filter['key_id'] = (empty($filter['key_id'])) ? 0 : $filter['key_id'];
			
			$select->columns(array(
				'id',
				'method',
				'unavailable' => new Expression("MAX(IF(AKSM.key_id = ?, 1, 0))", array($filter['key_id']))
			));
			unset($filter['key_id']);
			
			$select->join(array('S' => new TableIdentifier('api_services', $database)),
						  "{$table}.service_id = S.id",
						  array(
                                'service' => 'name'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('AKSM' => new TableIdentifier('api_key_service_methods', $database)),
						  "{$table}.id = AKSM.service_method_id",
						  array(),
						  Select::JOIN_LEFT);
			
			$columns = array(
				'key_id' => 'AKSM.key_id',
				'service' => 'S.name'
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array("S.name ASC", "{$table}.method ASC"), $columns)
				 ->applyRange($select, $range);
			$select->group(array("{$table}.id"));
			$select->having(array('unavailable' => 0));
			
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
	
	public function getAuto($identifiers) {
		$identifiers = $this->normalizeIdentifiers($identifiers);
		$result = $this->filterAuto($identifiers, array(), array(), $total);
		return $result->current();
	}
    
	public function filterAuto($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		$security_resource_app_database = $this->security_resource_app_database;
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database, $security_resource_app_database) {
			$select->columns(array(
				'service_method_id' => 'id',
				'service_method' => 'method',
				'service_id' => 'service_id'
			));
			
			$select->join(array('S' => new TableIdentifier('api_services', $database)),
						  "{$table}.service_id = S.id",
						  array(
								'service' => 'name'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('SR' => new TableIdentifier('security_resources', $security_resource_app_database)),
						  "{$table}.security_resource_id = SR.id",
						  array(),
						  Select::JOIN_LEFT);
			
			$columns = array(
				'app' => 'S.app',
				'service' => 'S.name',
				'service_version' => 'S.version',
				'service_method_id' => "{$table}.id",
				'service_method' => "{$table}.method"
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
}
?>