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

class ApiKeyServiceMethods extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = null;
	
	public function __construct(Adapter $dbh, $database = 'api') {
		parent::__construct($dbh, new TableIdentifier('api_key_service_methods', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['api']);
	}
	
	public function insert($key_id, $service_method_id) {
		return $this->_insert(array(
            'key_id' => $key_id,
            'service_method_id' => $service_method_id,
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
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database) {
			$select->join(array('SM' => new TableIdentifier('api_service_methods', $database)),
						  "{$table}.service_method_id = SM.id",
						  array(
                                'service_method' => 'method'
						  ),
						  Select::JOIN_LEFT);
			$select->join(array('S' => new TableIdentifier('api_services', $database)),
						  "SM.service_id = S.id",
						  array(
								'service_id' => 'id',
                                'service' => 'name'
						  ),
						  Select::JOIN_LEFT);
            
			$columns = array(
                'service_method' => 'SM.method',
				'service_version' => 'S.version',
                'service' => 'S.name'
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array("{$table}.key_id ASC"), $columns)
				 ->applyRange($select, $range);				 
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
}
?>