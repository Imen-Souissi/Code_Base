<?php
namespace Ticket\Gearman\Worker;

use Els\Gearman\Worker;
use Zend\Json\Json;
use Zend\Log\Logger;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Model\ViewModel;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Where;

use Ticket\Footprints\Api;
use Ticket\Footprints\Cache;

use Ticket\Model\FootprintsTickets;
use Ticket\Model\FootprintsTicketAssignees;
use Ticket\Model\FootprintsUsers;

use Hr\Model\HrUsers;

class Manager extends Worker {
	const NAME = 'ticket_manager';
	
	const EDIT = 'edit';
	const WIP = 'wip';
	const PENDING_CUSTOMER = 'pending-customer';
	const RESOLVED = 'resolved';
	const UPDATE_RECEIVE = 'update-receive';
	
	public function __construct($version = null) {
		parent::__construct(self::NAME, $version);
	}
	
	public static function factory(ServiceLocatorInterface $sm, $gearman_field = 'gearman') {
		$config = $sm->get('Config');
		$gearman_config = $config[$gearman_field];
		$version = $gearman_config['version'];
		
		return new self($version);
	}
	
	/**
	 * Process the given job.  The expected workload is a JSON string containing the below data structure.
	 * 	array( 		
	 * 		'action' => 'edit|wip|pending-customer|resolved|update-receive',
	 * 		'ticket' => '#',
	 * 		'submitter' => '',
	 * 		'status' => '' // only need if action is edit
	 *		'description' => '',
	 *		'params' => array(
	 *			// additional parameters for Footprints ticket	
	 *		),
	 *		'only_active' => true | false // execute only if ticket is not _DELETED_ or Closed
	 * 	)
	 * */
	public function process($job) {
		$sm = $this->getServiceLocator();
		$config = $sm->get('Config');
		
		$system_domain = $config['system']['domain'];
		$system_domain = (empty($system_domain)) ? 'brocade.com' : $system_domain;
		
		$logger = $this->getLogger();
		$result = array();
		
		$hr_users_mdl = HrUsers::factory($sm);
		
		$footprints_tickets_mdl = FootprintsTickets::factory($sm);
		$footprints_ticket_assignees_mdl = FootprintsTicketAssignees::factory($sm);
		$footprints_users_mdl = FootprintsUsers::factory($sm);
		
		$data = $job->workload();
		$logger->log(Logger::INFO, "received manager workload");
		
		try {
			$api = Api::factory($sm);
			$cache = Cache::factory($sm);
			
			$data = Json::decode($data, Json::TYPE_ARRAY);
			if(empty($data['ticket'])) {
				throw new \Exception("unable to process ticket without 'Ticket'");
			}
			if(empty($data['submitter'])) {
				throw new \Exception("unable to process ticket without 'Submitter'");
			}
			if(empty($data['action'])) {
				throw new \Exception("unable to process ticket without 'Action'");
			}
			
			$ticket = $data['ticket'];
			$submitter = $data['submitter'];
			$submitter_id = $data['submitter_id'];
			$submitter_footprint_user_id = null;
			$action = $data['action'];
			
			$description = $data['description'];
			$params = ($data['params']) ? $data['params'] : array();
			
			$continue_processing = true;
			$status = 0;
			
			$submitter_is_assignee = false;
			
			if(empty($submitter_id)) {
				$hr_user = $hr_users_mdl->get(array(
					'username' => $submitter,
					'domain' => $system_domain
				));
				
				if($hr_user) {
					$fp_user = $footprints_users_mdl->get(array(
						'username' => $submitter,
						'hr_user_id' => $hr_user->id
					));
					
					if($fp_user) {
						$submitter_footprint_user_id = $fp_user->id;
					} else {
						$submitter_footprint_user_id = $footprints_users_mdl->insert(
							$submitter,
							$hr_user->id
						);
					}
				} else {
					$submitter_user = $footprints_users_mdl->get(array(
						'username' => $submitter
					));
					
					if($submitter_user) {
						$submitter_footprint_user_id = $submitter_user->id;
					}
				}
			}
			
			if($data['only_active'] !== false) {
				// we will check and make sure this ticket is not deleted/closed
				$cache->cacheTicket($ticket);
				$footprints_ticket = $footprints_tickets_mdl->getDetail($ticket);
				
				if($footprints_ticket === false || in_array($footprints_ticket->status, array('_DELETED_', 'Closed'))) {
					$continue_processing = false;
				}
				
				// check if the submitter and the assignees are the same person, if not we should ensure that notifications are sent to the assignees too
				$assignee = $footprints_ticket_assignees_mdl->get(array(
					'ticket' => $ticket,
					'user_id' => $submitter_footprint_user_id
				));
				
				if($assignee !== false) {					
					// submitter is an assignee
					$submitter_is_assignee = true;
				}
			}
			
			if($continue_processing) {
				$logger->log(Logger::INFO, "processing {$action} on {$ticket}");
				
				switch($action) {
					case self::EDIT:						
						$status = (empty($data['status'])) ? null : $data['status'];
						if(!empty($description)) {
							$params['description'] = $description;
						}
						
						if($api->edit($ticket, $submitter, $status, $params)) {
							$status = 1;
						}
						break;
					case self::PENDING_CUSTOMER:
						if($api->pendingCustomer($ticket, $submitter, $description, $params)) {
							$status = 1;
						}
						break;
					case self::WIP:
						$description = (empty($description)) ? 'WIP' : $description;
						if($api->wip($ticket, $submitter, $description, $params)) {
							$status = 1;
						}
						break;
					case self::RESOLVED:
						// resolve the ticket.  if the submitter is not an assignee, we will trigger notification to the assignees
						if($api->resolved($ticket, $submitter, $description, !$submitter_is_assignee, $params)) {
							$status = 1;
						}
						break;
					case self::UPDATE_RECEIVE:
						if($api->updateReceive($ticket, $submitter, $description, $params)) {
							$status = 1;
						}
						break;
					default:
						// unknown action
						throw new \Exception("invalid action '{$action}' provided");
						break;					
				}
			}
			
			if($status == 1) {
				$logger->log(Logger::INFO, "successfully processed {$action} on {$ticket}");
			}
			
			$result = array(
				'status' => $status
			);
		} catch(\Exception $e) {			
			$logger->log(Logger::ERR, $e->getMessage());
			$logger->log(Logger::DEBUG, $e->getTraceAsString());
			
			throw $e;
		}
		
		return Json::encode($result);
	}
}
?>