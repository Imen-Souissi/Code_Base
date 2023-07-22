<?php
namespace Contract\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\TableIdentifier;

use Els\Mvc\Model\DbModel;
use Els\Mvc\Model\DbModelFactoryInterface;

use Zend\ServiceManager\ServiceLocatorInterface;

class ContractContactLinks extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = 'id';
	
	public function __construct(Adapter $dbh, $database = 'contract') {
		parent::__construct($dbh, new TableIdentifier('contract_contact_links', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['contract']);
	}
	
	public function insert($contract_id, $contact_id) {
		return $this->_insert(array(
			'contract_id' => $contract_id,
			'contact_id' => $contact_id
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
			$select->join(array('VC' => new TableIdentifier('vendor_contacts', $database)),
					"{$table}.contact_id = VC.id",
					array(
						'description',
						'url',
						'phone_number',
						'email',
						'notes'
					),
					Select::JOIN_LEFT);
			
			$columns = array(
				'description' => 'VC.description',
				'url' => 'VC.url',
				'phone_number' => 'VC.phone_number',
				'email' => 'VC.email',
				'notes' => 'VC.notes'
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