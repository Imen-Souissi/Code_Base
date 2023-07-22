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

class ApiKeys extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = 'id';
	protected $hr_database;
	protected $security_resource_app_database;
	
	public function __construct(Adapter $dbh, $database = 'api', $hr_database = 'hr', $security_resource_app_database = 'api') {
		$this->hr_database = $hr_database;
		$this->security_resource_app_database = $security_resource_app_database;
		
		parent::__construct($dbh, new TableIdentifier('api_keys', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		$security_resource_api = $config['api']['security_resource_app'];
		$security_resource_api = (empty($security_resource_api)) ? 'api' : $security_resource_api;
		
		return new self($dbh, $databases['api'], $databases['hr'], $databases[$security_resource_api]);
	}
	
	public function insert(
		$app,
		$key,
		$user_id,
		$description,
		$active = 1,
		$auto_configure = 1,
		$total_services = 0,
		$total_service_methods = 0
	) {
		return $this->_insert(array(
			'app' => $app,
            'key' => $key,
            'user_id' => $user_id,
			'description' => $description,
            'active' => $active,
			'auto_configure' => $auto_configure,
			'total_services' => $total_services,
			'total_service_methods' => $total_service_methods,
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
		$hr_database = $this->hr_database;
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database, $hr_database) {
			$select->join(array('U' => new TableIdentifier('hr_users', $hr_database)),
						  "{$table}.user_id = U.id",
						  array(
								'user' => 'display_name'
						  ),
						  Select::JOIN_LEFT);
			
			$columns = array(
				'user' => 'U.display_name'
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
	
	public function filterUserSecurityResources($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		$security_resource_app_database = $this->security_resource_app_database;
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database, $security_resource_app_database) {
			$select->columns(array());
			$select->join(array('SURL' => new TableIdentifier('security_user_role_links', $security_resource_app_database)),
						  "{$table}.user_id = SURL.user_id",
						  array(),
						  Select::JOIN_LEFT);
			$select->join(array('SRPL' => new TableIdentifier('security_role_permission_links', $security_resource_app_database)),
						  "SURL.role_id = SRPL.role_id",
						  array(),
						  Select::JOIN_LEFT);
			$select->join(array('SPRL' => new TableIdentifier('security_resource_permission_links', $security_resource_app_database)),
						  "SRPL.permission_id = SPRL.permission_id",
						  array(
								'resource_id'
						  ),
						  Select::JOIN_LEFT);
			
			$columns = array(
				'resource_id' => 'SPRL.resource_id'
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array("{$table}.id ASC"), $columns)
				 ->applyRange($select, $range);
			$select->group(array("SPRL.resource_id"));
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
	
	public function filterGroupSecurityResources($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		$hr_database = $this->hr_database;
		$security_resource_app_database = $this->security_resource_app_database;
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database, $hr_database, $security_resource_app_database) {
			$select->columns(array());
			$select->join(array('HU' => new TableIdentifier('hr_users', $hr_database)),
						  "{$table}.user_id = HU.id",
						  array(),
						  Select::JOIN_LEFT);
			$select->join(array('HUGL' => new TableIdentifier('hr_user_group_links', $hr_database)),
						  "HU.id = HUGL.user_id",
						  array(),
						  Select::JOIN_LEFT);
			$select->join(array('SGRL' => new TableIdentifier('security_group_role_links', $security_resource_app_database)),
						  "HUGL.group_id = SGRL.group_id",
						  array(),
						  Select::JOIN_LEFT);
			$select->join(array('SRPL' => new TableIdentifier('security_role_permission_links', $security_resource_app_database)),
						  "SGRL.role_id = SRPL.role_id",
						  array(),
						  Select::JOIN_LEFT);
			$select->join(array('SPRL' => new TableIdentifier('security_resource_permission_links', $security_resource_app_database)),
						  "SRPL.permission_id = SPRL.permission_id",
						  array(
								'resource_id'
						  ),
						  Select::JOIN_LEFT);
			
			$columns = array(
				'resource_id' => 'SPRL.resource_id'
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array("{$table}.id ASC"), $columns)
				 ->applyRange($select, $range);
			$select->group(array("SPRL.resource_id"));
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
	
	public function computeStats($identifiers) {
		$identifiers = $this->normalizeIdentifiers($identifiers);		
		$cnt = 0;
		
		$result = $this->filterServiceStats($identifiers, array(), array(), $total);
		foreach($result as $row) {
			$cnt += $this->update($row->id, array(
				'total_services' => $row->total_services,
				'total_service_methods' => $row->total_service_methods
			));
		}
		
		return $cnt;
	}
	
	public function filterServicesStats($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database) {
			$select->columns(array(
				'id',
				'total_services' => new Expression("COUNT(DISTINCT ASM.service_id)"),
				'total_service_methods' => new Expression("COUNT(DISTINCT AKSM.service_method_id")
			));
			$select->join(array('AKSM' => new TableIdentifier('api_key_service_methods', $database)),
						  "{$table}.id = AKSM.key_id",
						  array(),
						  Select::JOIN_LEFT);
			$select->join(array('ASM' => new TableIdentifier('api_service_methods', $database)),
						  "AKSM.service_method_id = ASM.id",
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