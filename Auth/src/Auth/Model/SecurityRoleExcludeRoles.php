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

class SecurityRoleExcludeRoles extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = 'id';
	
	public function __construct(Adapter $dbh, $database = 'app') {
		parent::__construct($dbh, new TableIdentifier('security_role_exclude_roles', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['app']);
	}
	
	public function insert(
        $role_id,
        $exclude_role_id
    ) {
		return $this->_insert(array(
            'role_id' => $role_id,
            'exclude_role_id' => $exclude_role_id,
			'ctime' => new Expression("NOW()")
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
				 ->applySort($select, $sort, array(), $columns)
				 ->applyRange($select, $range);
			$select->group(array());
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
    
    public function getExcluded($identifiers) {
        $identifiers = $this->normalizeIdentifiers($identifiers);
        
        $result = $this->filterExcluded($identifiers, array(), array(), $total);
        return $result->current();
    }
    
	public function filterExcluded($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database) {
            $select->join(array('SR' => new TableIdentifier('security_roles', $database)),
                          "{$table}.exclude_role_id = SR.id",
                          array(
                                'exclude_role' => 'name',
                                'exclude_description' => 'description'
                          ),
                          Select::JOIN_LEFT);
            
			$columns = array(
                'exclude_role' => 'SR.name',
                'exclude_description' => 'SR.description',
                'rights_level' => 'SR.rights_level'
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array(), $columns)
				 ->applyRange($select, $range);
			$select->group(array());
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
}
?>