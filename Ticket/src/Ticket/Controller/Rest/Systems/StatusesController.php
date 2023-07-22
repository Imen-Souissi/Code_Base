<?php
namespace Ticket\Controller\Rest\Systems;

use Els\Mvc\Controller\AbstractRestfulController;
use Ticket\Model\Systems;
use Ticket\Model\FootprintsStatuses;
use Ticket\Model\SalesforceStatuses;

class StatusesController extends AbstractRestfulController {
	public function getList() {
		$sm = $this->getServiceLocator();
        $systems_mdl = Systems::factory($sm);
		
        $system = $systems_mdl->get(array('id' => $this->params()->fromRoute('system_id')));
        if (!empty($system)) {
            if ($system->name == Systems::FOOTPRINTS) {
                $footprints_statuses_mdl = FootprintsStatuses::factory($sm);
                $items = $footprints_statuses_mdl->filter($this->getFilter(), $this->getRange(), $this->getSort(), $total);
                return $this->prepResult($items, $total);   
            } else if ($system->name == Systems::SALESFORCE) {
                $salesforce_statuses_mdl = SalesforceStatuses::factory($sm);
                $items = $salesforce_statuses_mdl->filter($this->getFilter(), $this->getRange(), $this->getSort(), $total);
                return $this->prepResult($items, $total);   
            }
        }
        
        return $this->notFoundAction();
	}
	
	public function get($id) {
        $sm = $this->getServiceLocator();
        $systems_mdl = Systems::factory($sm);
        
        $system = $systems_mdl->get(array('id' => $this->params()->fromRoute('system_id')));
        if (!empty($system)) {
            if ($system->name == Systems::FOOTPRINTS) {
                $footprints_statuses_mdl = FootprintsStatuses::factory($sm);
                return $footprints_statuses_mdl->get($id);
            } else if ($system->name == Systems::SALESFORCE) {
                $salesforce_statuses_mdl = SalesforceStatuses::factory($sm);
                return $salesforce_statuses_mdl->get($id);
            }
        }
        
        return $this->notFoundAction();
	}
}
?>