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

class CalendarQuarters extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = 'id';
	
	public function __construct(Adapter $dbh, $database = 'contract') {
		parent::__construct($dbh, new TableIdentifier('calendar_quarters', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['contract']);
	}

	public function insert($start_date, $end_date, $fiscal_year, $fiscal_quarter) {
		return $this->_insert(array(
			'start_date' => $start_date,
			'end_date' => $end_date,
			'fiscal_year' => $fiscal_year,
			'fiscal_quarter' => $fiscal_quarter
		));
	}
	
	public function get($identifiers) {
		$identifiers = $this->normalizeIdentifiers($identifiers);
		$result = $this->filter($identifiers, array(), array(), $total);
		return $result->current();
	}
	
	public function getPaymentByQuarter($identifiers) {
		$identifiers = $this->normalizeIdentifiers($identifiers);
		$result = $this->filterPaymentByQuarter($identifiers, array(), array(), $total);
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

	public function filterPaymentByQuarter($filter, $range, $sort, &$total) {
		$self = $this;
		$table = $this->getTableName();
		$database = $this->getDatabaseName();
	
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table, $database) {
			$select->join(array('P' => new TableIdentifier('payments', $database)),
					"P.payment_date BETWEEN {$table}.start_date AND {$table}.end_date",
					array(
					),
					Select::JOIN_LEFT);
			$select->join(array('PT' => new TableIdentifier('payment_types', $database)),
					"PT.id = P.payment_type_id",
					array(
						'payment_type' => 'name'
					),
					Select::JOIN_LEFT);
			$select->join(array('C' => new TableIdentifier('contracts', $database)),
					"C.id = P.contract_id",
					array(
					),
					Select::JOIN_LEFT);
			$select->join(array('CS' => new TableIdentifier('contract_statuses', $database)),
					"CS.id = C.status_id",
					array(
					),
					Select::JOIN_LEFT);
	
			$select->group("{$table}.fiscal_year");
			$select->group("{$table}.fiscal_quarter");
			
			$payment_type_text_paid = 'Paid';
			$payment_type_text_scheduled = 'Scheduled';
			$payment_type_text_renewal = 'Renewal';
			$status_text_deleted = 'Deleted';
			
			$payment_types_all_paid = array($payment_type_text_paid);
			$payment_types_all_scheduled = array($payment_type_text_scheduled, $payment_type_text_renewal);
			
			$vals1 = array_merge($payment_types_all_paid, array($status_text_deleted));
			$vals2 = array_merge($payment_types_all_scheduled, array($status_text_deleted));
			
			//all calendar_quarters related columns
			$columns = array(
				'id',
				'fiscal_year',
				'fiscal_quarter',
				'start_date',
				'end_date',
				'payments_count_paid' => new Expression("IFNULL(SUM(IF(PT.name=? AND CS.name !=?,1,0)),0)", $vals1), 
				'payments_amount_paid' => new Expression("IFNULL(SUM(IF(PT.name=? AND CS.name !=?,P.amount,0)),0)", $vals1),
				'payments_count_scheduled' => new Expression("IFNULL(SUM(IF((PT.name=? OR PT.name=?) AND CS.name !=?,1,0)),0)", $vals2),
				'payments_amount_scheduled' => new Expression("IFNULL(SUM(IF((PT.name=? OR PT.name=?) AND CS.name !=?,P.amount,0)),0)", $vals2)
			);
			
			if($filter['contract_id']) {
				$contract_ids = $filter['contract_id'];
				$placeholders = implode(',', array_fill(0, count($contract_ids), '?'));
				
				$vals1 = array_merge($payment_types_all_paid, array($status_text_deleted), $contract_ids);
				$vals2 = array_merge($payment_types_all_scheduled, array($status_text_deleted), $contract_ids); 
				
				$columns['payments_count_paid'] = new Expression("IFNULL(SUM(IF(PT.name=? AND CS.name !=? AND C.id IN ({$placeholders}),1,0)),0)", $vals1);
				$columns['payments_amount_paid'] = new Expression("IFNULL(SUM(IF(PT.name=? AND CS.name !=? AND C.id IN ({$placeholders}),P.amount,0)),0)", $vals1);
				$columns['payments_count_scheduled'] = new Expression("IFNULL(SUM(IF((PT.name=? OR PT.name=?) AND CS.name !=? AND C.id IN ({$placeholders}),1,0)),0)", $vals2);
				$columns['payments_amount_scheduled'] = new Expression("IFNULL(SUM(IF((PT.name=? OR PT.name=?) AND CS.name !=? AND C.id IN ({$placeholders}),P.amount,0)),0)", $vals2);
				
				unset($filter['contract_id']);
			}
			
			$select->columns($columns);
			
			//additional columns from joined tables
			$columns['contract_id'] = 'C.id';
			
			$where = new Where();

			$self->applyFilter($select, $where, $filter, $columns)
				->applySort($select, $sort, array("{$table}.fiscal_year ASC", "{$table}.fiscal_quarter ASC"), $columns)
				->applyRange($select, $range);
	
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
	
		$total = $this->getCalcFoundRows($result, $range);

		return $result;
	}
}
?>