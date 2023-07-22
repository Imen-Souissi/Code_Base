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

class Payments extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = 'id';
	protected $gem_database;
	
	public function __construct(Adapter $dbh, $database = 'contract', $gem_database = 'gem') {
		$this->gem_database = $gem_database;
		parent::__construct($dbh, new TableIdentifier('payments', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['contract'], $databases['gem']);
	}
	
	public function insert($contract_id, $amount, $payment_date, $payment_type_id, $payment_number, $num_months) {
		return $this->_insert(array(
			'contract_id' => $contract_id,
            'amount' => $amount,
			'payment_date' => $payment_date,
			'payment_type_id' => $payment_type_id,
			'payment_number' => $payment_number,
			'num_months' => $num_months,
			'ctime' => new Expression("NOW()"),
			'etime' => new Expression("NOW()")
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
		$gem_database = $this->gem_database;
		
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database, $gem_database) {
			if(empty($filter['access'])) {
				$filter['access'] = new Expression("1");
			}
				
			$select->columns(array(
					'*',
					'has_access' => $filter['access']
			));
			
			if(!empty($filter['access'])) {
				$having['has_access'] = 1;
			}
			unset($filter['access']);
			
			$select->join(array('PT' => new TableIdentifier('payment_types', $database)),
					"{$table}.payment_type_id = PT.id",
					array(
						'payment_type' => 'name'
					),
					Select::JOIN_INNER);
			$select->join(array('C' => new TableIdentifier('contracts', $database)),
					"{$table}.contract_id = C.id",
					array(
						'contract_next_payment_id' => 'next_payment_id',
						'contract_description' => 'description',
						'contract_number'
					),
					Select::JOIN_INNER);
			$select->join(array('CS' => new TableIdentifier('contract_statuses', $database)),
					"C.status_id = CS.id",
					array(
						'contract_status' => 'name',
					),
					Select::JOIN_INNER);
			$select->join(array('CT' => new TableIdentifier('contract_types', $database)),
					"C.type_id = CT.id",
					array(
						'contract_type' => 'name',
					),
					Select::JOIN_INNER);
			$select->join(array('V' => new TableIdentifier('vendors', $database)),
					"C.vendor_id = V.id",
					array(
						'contract_vendor' => 'name',
					),
					Select::JOIN_INNER);
			$select->join(array('CUL' => new TableIdentifier('contract_user_links', $database)),
					"C.id = CUL.contract_id",
					array(
					),
					Select::JOIN_LEFT);
			$select->join(array('LVC' => new TableIdentifier('labview_contracts', $gem_database)),
					"C.id = LVC.contract_id",
					array(),
					Select::JOIN_LEFT);
			
			$columns = array(
				'payment_type' => 'PT.name',
				'contract_next_payment_id' => 'C.next_payment_id',
				'contract_description' => 'C.description',
				'contract_number' => 'C.contract_number',
				'contract_status' => 'CS.name',
				'contract_type' => 'CT.name',
				'contract_vendor' => 'V.name',
				'labview_id' => 'LVC.labview_id'
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array("{$table}.id ASC"), $columns)
				 ->applyRange($select, $range);
			$select->group("{$table}.id");
			$select->having($having);
				 
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
}
?>