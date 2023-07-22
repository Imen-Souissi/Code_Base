<?php
namespace Ticket\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\TableIdentifier;

use Els\Mvc\Model\DbModel;
use Els\Mvc\Model\DbModelFactoryInterface;

use Zend\ServiceManager\ServiceLocatorInterface;

class FootprintsTicketDetails extends DbModel implements DbModelFactoryInterface {
	protected $primary_key = 'ticket';
	protected $hr_database;
	
	public function __construct(Adapter $dbh, $database = 'ticket', $hr_database = 'hr') {
		$this->hr_database = $hr_database;
		parent::__construct($dbh, new TableIdentifier('footprints_ticket_details', $database));
	}
	
	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];
		
		return new self($dbh, $databases['ticket'], $databases['hr']);
	}
	
	public function insert(
        $ticket,
        $descriptions,
        $all_descriptions,
        $resolutions,
        $agent_logs
    ) {
		return $this->_insert(array(
			'ticket' => $ticket,
			'descriptions' => $descriptions,
			'all_descriptions' => $all_descriptions,
			'resolutions' => $resolutions,
			'agent_logs' => $agent_logs,
			'etime' => new Expression("NOW()")
		));
	}
	
	public function replace(
        $ticket,
        $descriptions,
        $all_descriptions,
        $resolutions,
        $agent_logs
    ) {
		return $this->_replace(array(
			'ticket' => $ticket,
			'descriptions' => $descriptions,
			'all_descriptions' => $all_descriptions,
			'resolutions' => $resolutions,
			'agent_logs' => $agent_logs,
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
		$result = $this->getGateway()->select(function($select) use($self, $filter, $range, $sort, $table) {
			$columns = array(
			);
			$where = new Where();
			$self->applyFilter($select, $where, $filter, $columns)
				 ->applySort($select, $sort, array(), $columns)
				 ->applyRange($select, $range);
				 
			//echo $self->getGateway()->getSql()->getSqlStringForSqlObject($select);
		});
		
		$total = $this->getCalcFoundRows($result, $range);
		
		return $result;
	}
}
?>