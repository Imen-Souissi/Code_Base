<?php
namespace Ticket\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Ticket\Model\FootprintsTickets;

class TicketsController extends AbstractRestfulController {
	public function getList() {
		$sm = $this->getServiceLocator();		
		$footprints_tickets_mdl = FootprintsTickets::factory($sm);
		
		$items = $footprints_tickets_mdl->filterSimple($this->getFilter(), $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$footprints_tickets_mdl = FootprintsTickets::factory($this->getServiceLocator());
		return $footprints_tickets_mdl->getSimple($id);
	}
}
?>