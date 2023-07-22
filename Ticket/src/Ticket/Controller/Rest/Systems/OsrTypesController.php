<?php
namespace Ticket\Controller\Rest\Systems;

use Els\Mvc\Controller\AbstractRestfulController;
use Ticket\Model\Systems;
use Ticket\Model\SalesforceLabLocations;

class OsrTypesController extends AbstractRestfulController {
	public function getList() {
        return array('data' => array(
            array('id' => 1, 'name' => 'Lab Ticket'),
            array('id' => 2, 'name' => 'Facilities')
        ));
	}

	public function get($id) {
        return array('data' => array(
            array('id' => 1, 'name' => 'Lab Ticket'),
            array('id' => 2, 'name' => 'Facilities')
        ));
	}
}
?>
