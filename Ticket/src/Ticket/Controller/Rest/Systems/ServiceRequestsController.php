<?php
namespace Ticket\Controller\Rest\Systems;

use Els\Mvc\Controller\AbstractRestfulController;
use Ticket\Model\Systems;
use Ticket\Model\ServiceRequests;
use Ticket\Model\FootprintsStatuses;
use Ticket\Model\SalesforceStatuses;

class ServiceRequestsController extends AbstractRestfulController {
	public function getList() {
		$sm = $this->getServiceLocator();
        $systems_mdl = Systems::factory($sm);
		$service_requests_mdl = ServiceRequests::factory($sm);
		
        $system = $systems_mdl->get(array('id' => $this->params()->fromRoute('system_id')));
        if (!empty($system)) {
            $filter = $this->getFilter();
            if (isset($filter['status_id'])) {
                if (intval($filter['status_id']) === -1) {
                    // show all
                    unset($filter['status_id']);
                }
            }
            
            if (!$this->authorization()->isPermitted('Ticket::ServiceRequests', 'access-all')) {
                $filter['hr_contact_id'] = $this->authentication()->getAuthenticatedUserId();
            }
            
            if ($system->name == Systems::FOOTPRINTS) {
                if (isset($filter['status_id']) && intval($filter['status_id']) === 0) {
                    // show only active
                    $statuses_mdl = FootprintsStatuses::factory($sm);
                    $statuses = $statuses_mdl->filter(array(
                        'name' => array(
                            'Pending - Customer',
                            'Update Received',
                            'Assigned',
                            'WIP',
                            'Pending - Other',
                            'Open',
                            'Scheduled'
                        )
                    ), array(), array(), $total)->toArray();
                    
                    $filter['status_id'] = array_map(function($status) {
                        return $status['id'];
                    }, $statuses);
                }
                
                $items = $service_requests_mdl->filterFootprints($filter, $this->getRange(), $this->getSort(), $total);
                return $this->prepResult($items, $total);   
            } else if ($system->name == Systems::SALESFORCE) {
                if (isset($filter['status_id']) && intval($filter['status_id']) === 0) {
                    // show only active
                    $statuses_mdl = SalesforceStatuses::factory($sm);
                    $statuses = $statuses_mdl->filter(array(
                        'name' => array(
                            'Committed',
                            'Investigating',
                            'Logical Setup',
                            'Open',
                            'Physical Setup',
                            'Tentative'
                        )
                    ), array(), array(), $total)->toArray();
                    
                    $filter['status_id'] = array_map(function($status) {
                        return $status['id'];
                    }, $statuses);
                }
                
                $items = $service_requests_mdl->filterSalesforce($filter, $this->getRange(), $this->getSort(), $total);
                return $this->prepResult($items, $total);   
            }
        }
        
        return $this->notFoundAction();
	}
	
	public function get($id) {
        $sm = $this->getServiceLocator();
        $systems_mdl = Systems::factory($sm);
		$service_requests_mdl = ServiceRequests::factory($sm);
        
        $system = $systems_mdl->get(array('id' => $this->params()->fromRoute('system_id')));
        if (!empty($system)) {
            if ($system->name == Systems::FOOTPRINTS) {
                return $service_requests_mdl->getFootprints($id);
            } else if ($system->name == Systems::SALESFORCE) {
                return $service_requests_mdl->getSalesforce($id);
            }
        }
        
        return $this->notFoundAction();
	}
}
?>