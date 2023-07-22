<?php
namespace Auth\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\TableIdentifier;

use Els\Mvc\Model\DbModel;
use Els\Mvc\Model\DbModelFactoryInterface;

use Zend\ServiceManager\ServiceLocatorInterface;

class SecurityResources extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = 'id';
	
	public function __construct(Adapter $dbh, $database = 'app') {
		parent::__construct($dbh, new TableIdentifier('security_resources', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['app']);
	}
	
	public function insert(
		$controller,
		$action
	) {
		return $this->_insert(array(
			'controller' => $controller,
			'action' => $action,
			'ctime' => new Expression("NOW()")
		));
	}
	
	public function filter($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database) {
			$select->join(array('RPL' => new TableIdentifier('security_resource_permission_links', $database)),
						  "{$table}.id = RPL.resource_id",
						  array(
								'permissions' => new Expression("COUNT(DISTINCT RPL.permission_id)")
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('RPL2' => new TableIdentifier('security_role_permission_links', $database)),
						  "RPL.permission_id = RPL2.permission_id",
						  array(),
						  Select::JOIN_LEFT);
			$select->join(array('R' => new TableIdentifier('security_roles', $database)),
						  "RPL2.role_id = R.id",
						  array(),
						  Select::JOIN_LEFT);
			
			$columns = array(
				'permission_id' => 'RPL.permission_id',
				'role_id' => 'RPL2.role_id',
				'rights_level' => 'R.rights_level'
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array(), $columns)
				 ->applyRange($select, $range);
			$select->group(array("{$table}.id"));
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
	
	public function filterController($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database) {
			$select->columns(array(
				'controller'
			));
			
			$columns = array(
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array(), $columns)
				 ->applyRange($select, $range);
			$select->group(array("{$table}.controller"));
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
	
	public function filterAvailable($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database) {
			$filter['permission_id'] = (empty($filter['permission_id'])) ? 0 : $filter['permission_id'];
			
			$select->columns(array(
				'*',
				'unavailable' => new Expression("MAX(IF(RPL.permission_id = ?, 1, 0))", array($filter['permission_id']))
			));
			unset($filter['permission_id']);
			
			$select->join(array('RPL' => new TableIdentifier('security_resource_permission_links', $database)),
						  "{$table}.id = RPL.resource_id",
						  array(
								'permissions' => new Expression("COUNT(DISTINCT RPL.permission_id)")
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('RPL2' => new TableIdentifier('security_role_permission_links', $database)),
						  "RPL.permission_id = RPL2.permission_id",
						  array(),
						  Select::JOIN_LEFT);
			$select->join(array('R' => new TableIdentifier('security_roles', $database)),
						  "RPL2.role_id = R.id",
						  array(),
						  Select::JOIN_LEFT);
			
			$columns = array(
				'permission_id' => 'RPL.permission_id',
				'role_id' => 'RPL2.role_id',
				'rights_level' => 'R.rights_level'
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array(), $columns)
				 ->applyRange($select, $range);
			$select->group(array("{$table}.id"));
			$select->having(array('unavailable' => 0));
			
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
	
	public function filterAccess($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database) {
			$select->join(array('RPL' => new TableIdentifier('security_resource_permission_links', $database)),
						  "{$table}.id = RPL.resource_id",
						  array(),
						  Select::JOIN_LEFT);
			$select->join(array('RPL2' => new TableIdentifier('security_role_permission_links', $database)),
						  "RPL.permission_id = RPL2.permission_id",
						  array(),
						  Select::JOIN_LEFT);
			
			$columns = array(
				'permission_id' => 'RPL.permission_id',
				'role_id' => 'RPL2.role_id'
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array(), $columns)
				 ->applyRange($select, $range);
			$select->group(array("{$table}.id"));
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
}
?>