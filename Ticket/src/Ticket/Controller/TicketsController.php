<?php
namespace Ticket\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Log\Logger;

use Ticket\Model\FootprintsTickets;
use Ticket\Model\FootprintsTicketLinks;
use Ticket\Model\FootprintsTicketDetails;
use Ticket\Footprints\Api;
use Ticket\Footprints\Cache;

class TicketsController extends AbstractActionController {
	public function indexAction() {
		return new ViewModel(array());
	}
	
	public function viewAction() {
		$sm = $this->getServiceLocator();
		$id = $this->params()->fromRoute('id');
		
		$view = new ViewModel(array(
			'id' => $id
		));
		
		$footprints_tickets_mdl = FootprintsTickets::factory($sm);
		$footprints_ticket_links_mdl = FootprintsTicketLinks::factory($sm);
		$footprints_ticket_details_mdl = FootprintsTicketDetails::factory($sm);
		
		$ticket = $footprints_tickets_mdl->getDetail($id);		
		if($ticket === false) {
			// attempt to cache it
			$cache = Cache::factory($sm);
			try {
				$cache->cacheTicket($id);
			} catch(\Exception $e) {
				// do nothing	
			}
			
			// now retry to grab it from our cache database
			$ticket = $footprints_tickets_mdl->getDetail($id);
		}
		
		$ticket = ($ticket !== false) ? $ticket->getArrayCopy() : array();
		$detail = $footprints_ticket_details_mdl->get($id);
		if($detail) {
			$ticket = array_merge($ticket, $detail->getArrayCopy());
		}
		
		// verify if this ticket has any subtasks
		$footprints_ticket_links_mdl->filter(array(
			'ticket' => $id,
			'!type' => FootprintsTicketLinks::TYPE_PARENT
		), array(), array(), $total);
		
		$view->setVariable('subtasks', $total);
		
		// verify if this ticket has any master tasks
		$footprints_ticket_links_mdl->filter(array(
			'ticket' => $id,
			'type' => FootprintsTicketLinks::TYPE_PARENT
		), array(), array(), $total);
		
		$view->setVariable('masters', $total);
		
		$view->setVariable('ticket', $ticket);
		
		return $view;
	}
	
	public function syncAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');
		
		$id = $this->params()->fromRoute('id');
		
		$status = 0;
		$error = "";
		
		try {
			$cache = Cache::factory($sm);
			$cache->cacheTicket($id);
			
			$status = 1;
		} catch(\Exception $e) {
			$logger->log(Logger::ERR, "unable to sync footprints ticket : " . $e->getMessage());
			$p = $e->getPrevious();
			if($p) {
				$e = $p;
			}
			
			$error = $e->getMessage();
			echo $e->getTraceAsString();
		}
		
		return new JsonModel(array(
			'id' => $id,
			'status' => $status,
			'error' => $error
		));
	}
}
?>