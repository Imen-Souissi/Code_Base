<?php
namespace Power\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\TableIdentifier;

use Els\Mvc\Model\DbModel;
use Els\Mvc\Model\DbModelFactoryInterface;

use Zend\ServiceManager\ServiceLocatorInterface;

class PowerPdus extends DbModel {
	protected $primary_key = 'id';

	public function __construct(Adapter $dbh, $database = 'power') {
		parent::__construct($dbh, new TableIdentifier('power_pdus', $database));
	}

	public static function factory(ServiceLocatorInterface $serviceManager, $dbfield = 'db', $dbsfield = 'databases') {
		$dbh = $serviceManager->get($dbfield);
		$config = $serviceManager->get('Config');
		$databases = $config[$dbsfield];

		return new self($dbh, $databases['power']);
	}

	public function insert(
        $power_iq_pdu_id,
        $ip,
        $hostname,
        $manufacturer,
        $model,
        $phase,
        $type,
        $power_iq_rack_id,
        $rack_id,
        $volt,
        $current,
        $power,
        $connectivity,
        $rtime
    ) {
		return $this->_insert(array(
			'power_iq_pdu_id' => $power_iq_pdu_id,
            'ip' => $ip,
            'hostname' => $hostname,
            'manufacturer' => $manufacturer,
            'model' => $model,
            'phase' => $phase,
            'type' => $type,
            'power_iq_rack_id' => $power_iq_rack_id,
            'rack_id' => $rack_id,
            'volt' => $volt,
            'current' => $current,
            'power' => $power,
            'connectivity' => $connectivity,
            'rtime' => $rtime,
			'ctime' => new Expression('NOW()'),
			'etime' => new Expression('NOW()')
		));
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
}
?>
