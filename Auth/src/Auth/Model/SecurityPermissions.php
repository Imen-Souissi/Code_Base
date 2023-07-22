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

class SecurityPermissions extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = 'id';
	
	public function __construct(Adapter $dbh, $database = 'app') {
		parent::__construct($dbh, new TableIdentifier('security_permissions', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['app']);
	}
	
	public function insert(
		$name,
		$description
	) {
		return $this->_insert(array(
			'name' => $name,
			'description' => $description,
			'ctime' => new Expression("NOW()")
		));
	}
	
	public function filter($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database) {
			$select->join(array('RPL' => new TableIdentifier('security_resource_permission_links', $database)),
						  "{$table}.id = RPL.permission_id",
						  array(
								'resources' => new Expression("COUNT(DISTINCT RPL.resource_id)")
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('RPL2' => new TableIdentifier('security_role_permission_links', $database)),
						  "{$table}.id = RPL2.permission_id",
						  array(
								'roles' => new Expression("COUNT(DISTINCT RPL2.role_id)")
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('R' => new TableIdentifier('security_roles', $database)),
						  "RPL2.role_id = R.id",
						  array(),
						  Select::JOIN_LEFT);
			
			$columns = array(
				'role_id' => 'RPL2.role_id',
				'resource_id' => 'RPL.resource_id',
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
	
	public function filterAvailable($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database) {
			$select->columns(array(
				'*',
				'resource_unavailable' => new Expression("MAX(IF(RPL.resource_id = ?, 1, 0))", array($filter['resource_id'])),
				'role_unavailable' => new Expression("MAX(IF(RPL2.role_id = ?, 1, 0))", array($filter['role_id'])),
			));
			
			$having = array();
			if($filter['role_id']) {
				$having['role_unavailable'] = 0;
			}
			if($filter['resource_id']) {
				$having['resource_unavailable'] = 0;
			}
			
			unset($filter['role_id']);
			unset($filter['resource_id']);
			
			$select->join(array('RPL' => new TableIdentifier('security_resource_permission_links', $database)),
						  "{$table}.id = RPL.permission_id",
						  array(),
						  Select::JOIN_LEFT);
			$select->join(array('RPL2' => new TableIdentifier('security_role_permission_links', $database)),
						  "{$table}.id = RPL2.permission_id",
						  array(),
						  Select::JOIN_LEFT);
			$select->join(array('R' => new TableIdentifier('security_roles', $database)),
						  "RPL2.role_id = R.id",
						  array(),
						  Select::JOIN_LEFT);
			
			$columns = array(
				'role_id' => 'RPL2.role_id',
				'resource_id' => 'RPL.resource_id',
				'rights_level' => 'R.rights_level'
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array(), $columns)
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