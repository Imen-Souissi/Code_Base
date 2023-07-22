<?php
namespace Ticket\Controller\Rest;

use Els\Mvc\Controller\AbstractRestfulController;
use Ticket\Model\FootprintsTicketLinks;

class SubTicketsController extends AbstractRestfulController {
	public function getList() {
		$sm = $this->getServiceLocator();		
		$footprints_ticket_links_mdl = FootprintsTicketLinks::factory($sm);
		
		$items = $footprints_ticket_links_mdl->filterTicket($this->getFilter(), $this->getRange(), $this->getSort(), $total);
		return $this->prepResult($items, $total);
	}
	
	public function get($id) {
		$footprints_ticket_links_mdl = FootprintsTicketLinks::factory($this->getServiceLocator());
		return $footprints_ticket_links_mdl->getTicket($id);
	}
}
?>