<?php
namespace Ticket\Controller\Rest\Systems;

use Els\Mvc\Controller\AbstractRestfulController;
use Ticket\Model\Systems;
use Ticket\Model\SalesforceLabLocations;

class LabLocationsController extends AbstractRestfulController {
	public function getList() {
		$sm = $this->getServiceLocator();
        $systems_mdl = Systems::factory($sm);
		
        $system = $systems_mdl->get(array('id' => $this->params()->fromRoute('system_id')));
        if (!empty($system)) {
            if ($system->name == Systems::SALESFORCE) {
                $salesforce_lab_locations_mdl = SalesforceLabLocations::factory($sm);
                $items = $salesforce_lab_locations_mdl->filter($this->getFilter(), $this->getRange(), $this->getSort(), $total);
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
            if ($system->name == Systems::SALESFORCE) {
                $salesforce_lab_locations_mdl = SalesforceLabLocations::factory($sm);
                return $salesforce_lab_locations_mdl->get($id);
            }
        }
        
        return $this->notFoundAction();
	}
}
?>