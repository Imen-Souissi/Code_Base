<?php
namespace Ticket\Footprints;

use Els\Db\Sql\Where;
use Zend\Db\Sql\Expression;
use Zend\Log\Logger;

use Ticket\Footprints\Api;

use Ticket\Model\FootprintsPriorities;
use Ticket\Model\FootprintsActivities;
use Ticket\Model\FootprintsCategories;
use Ticket\Model\FootprintsSubCategories;
use Ticket\Model\FootprintsSites;
use Ticket\Model\FootprintsLabs;
use Ticket\Model\FootprintsTypes;
use Ticket\Model\FootprintsEngProjects;
use Ticket\Model\FootprintsStatuses;
use Ticket\Model\FootprintsTickets;
use Ticket\Model\FootprintsTicketAssignees;
use Ticket\Model\FootprintsTicketDetails;
use Ticket\Model\FootprintsTicketInfodots;
use Ticket\Model\FootprintsTicketLinks;
use Ticket\Model\FootprintsUsers;

use Hr\Model\HrUsers;
use Hr\Model\HrDomains;

class Cache extends Api {
	protected $sm;
	protected $logger;
	protected $default_domain = 'brocade.com';
	
	public function __construct($host, $project_id, $username, $password, $sm) {
		$this->sm = $sm;
		parent::__construct($host, $project_id, $username, $password);
	}
	
	public static function factory($sm) {
		$config = $sm->get('Config');
		return new self(
			$config['footprints']['host'],
			$config['footprints']['project_id'],
			$config['footprints']['username'],
			$config['footprints']['password'],
			$sm
		);
	}
	
	public function setLogger($logger) {
		$this->logger = $logger;
	}
	
	public function hasLogger() {
		return !empty($this->logger);
	}
	
	public function cacheTicket($ticket, $row = null) {
		$footprints_tickets_mdl = FootprintsTickets::factory($this->sm);
		$footprints_priorities_mdl = FootprintsPriorities::factory($this->sm);
		$footprints_activities_mdl = FootprintsActivities::factory($this->sm);
		$footprints_sites_mdl = FootprintsSites::factory($this->sm);
		$footprints_labs_mdl = FootprintsLabs::factory($this->sm);
		$footprints_types_mdl = FootprintsTypes::factory($this->sm);
		$footprints_categories_mdl = FootprintsCategories::factory($this->sm);
		$footprints_sub_categories_mdl = FootprintsSubCategories::factory($this->sm);
		$footprints_eng_projects_mdl = FootprintsEngProjects::factory($this->sm);
		$footprints_statuses_mdl = FootprintsStatuses::factory($this->sm);		
		$footprints_ticket_assignees_mdl = FootprintsTicketAssignees::factory($this->sm);
		$footprints_ticket_details_mdl = FootprintsTicketDetails::factory($this->sm);
		$footprints_ticket_infodots_mdl = FootprintsTicketInfodots::factory($this->sm);
		$footprints_ticket_links_mdl = FootprintsTicketLinks::factory($this->sm);
		$footprints_users_mdl = FootprintsUsers::factory($this->sm);
		
		$hr_users_mdl = HrUsers::factory($this->sm);
		$hr_domains_mdl = HrDomains::factory($this->sm);
		
		if($row === null) {
			// dump this guy
			$row = $this->dump(array("M.MRID = '{$ticket}'"));
			if(is_array($row)) {
				$row = array_shift($row);
			}
		}
		
		if(empty($row)) {
			// unable to cache ticket
			throw new \Exception("unable to cache ticket, could not load from Footprints");
		}
		
		// pull the domain
		$domain = $hr_domains_mdl->get(array(
			'name' => $this->default_domain
		));
		
		if($domain === false) {
			$domain_id = $hr_domains_mdl->insert($this->default_domain);
		} else {
			$domain_id = $domain->id;
		}
		
		// pull the priority
		$priority = $row->priority;
		// parse the contact for the username
		$contact = $this->normalizeUsername($row->contact);
		// parse the submit by for the username
		$submit_by = $this->normalizeUsername($row->submit_by);
		// find the weight
		$weight = strtolower($row->weight);
		if($weight == "heavy") {
			$weight = $row->heavyweight;
		}
		// parse the status by for the username
		$status_by = $this->normalizeUsername($row->status_by);				
		
		// pull the activity
		$activity = self::decode($row->activity);
		// make sure this activity is in our database
		$record = $footprints_activities_mdl->get(array('name' => $activity));
		if($record === false) {
			// insert it
			$activity_id = $footprints_activities_mdl->insert($activity);
		} else {
			$activity_id = $record->id;
		}
		
		// pull the site
		$site = self::decode($row->site);
		// make sure this site is in our database
		$record = $footprints_sites_mdl->get(array('name' => $site));
		if($record === false) {
			// insert it
			$site_id = $footprints_sites_mdl->insert($site);
		} else {
			$site_id = $record->id;
		}
		
		// pull the lab
		$lab = self::decode($row->lab);
		// make sure this lab is in our database under this site
		$record = $footprints_labs_mdl->get(array(
			'site_id' => $site_id,
			'name' => $lab
		));
		if($record === false) {
			// insert it
			$lab_id = $footprints_labs_mdl->insert($site_id, $lab);
		} else {
			$lab_id = $record->id;
		}
		
		// pull the type
		$type = self::decode($row->type);
		// make sure this type is in our database
		$record = $footprints_types_mdl->get(array('name' => $type));
		if($record === false) {
			// insert it
			$type_id = $footprints_types_mdl->insert($type);
		} else {
			$type_id = $record->id;
		}
		
		// pull the category
		$category = self::decode($row->category);
		// make sure this category is in our database
		$record = $footprints_categories_mdl->get(array('name' => $category));
		if($record === false) {
			// insert it
			$category_id = $footprints_categories_mdl->insert($category);
		} else {
			$category_id = $record->id;
		}
		
		// pull the sub category
		$sub_category = self::decode($row->sub_category);
		// make sure this sub category is in our database under this category
		$record = $footprints_sub_categories_mdl->get(array(
			'category_id' => $category_id,
			'name' => $sub_category
		));
		if($record === false) {
			// insert it
			$sub_category_id = $footprints_sub_categories_mdl->insert($category_id, $sub_category);
		} else {
			$sub_category_id = $record->id;
		}
		
		// pull the eng project
		$eng_project = self::decode($row->eng_project);
		// make sure this eng project is in our database
		$record = $footprints_eng_projects_mdl->get(array('name' => $eng_project));
		if($record === false) {
			// insert it
			$eng_project_id = $footprints_eng_projects_mdl->insert($eng_project);
		} else {
			$eng_project_id = $record->id;
		}
		
		// pull the status
		$status = self::decode($row->status);
		// make sure this status is in our database
		$record = $footprints_statuses_mdl->get(array('name' => $status));
		if($record === false) {
			// insert it
			$status_id = $footprints_statuses_mdl->insert($status);
		} else {
			$status_id = $record->id;
		}
		
		// pull the contact_id
		$contact_id = null;
		$contact_user = $footprints_users_mdl->get(array(
			'username' => $contact
		));
		
		if($contact_user) {
			$contact_id = $contact_user->id;
		} else {
			$contact_hr_user = $hr_users_mdl->get(array(
				'username' => $contact,
				'domain_id' => $domain_id
			));
			
			$contact_id = $footprints_users_mdl->insert(
				// username
				$contact,
				// hr_user_id
				($contact_hr_user) ? $contact_hr_user->id : null
			);
		}
		
		// pull the submit_by_id
		$submit_by_id = null;
		$submit_by_user = $footprints_users_mdl->get(array(
			'username' => $submit_by
		));
		
		if($submit_by_user) {
			$submit_by_id = $submit_by_user->id;
		} else {
			$submit_by_hr_user = $hr_users_mdl->get(array(
				'username' => $submit_by,
				'domain_id' => $domain_id
			));
			
			$submit_by_id = $footprints_users_mdl->insert(
				// username
				$submit_by,
				// hr_user_id
				($submit_by_hr_user) ? $submit_by_hr_user->id : null
			);
		}
		
		// pull the status_by_id
		$status_by_id = null;
		$status_by_user = $footprints_users_mdl->get(array(
			'username' => $status_by
		));
		
		if($status_by_user) {
			$status_by_id = $status_by_user->id;
		} else {
			$status_by_hr_user = $hr_users_mdl->get(array(
				'username' => $status_by,
				'domain_id' => $domain_id
			));
			
			$status_by_id = $footprints_users_mdl->insert(
				// username
				$status_by,
				// hr_user_id
				($status_by_hr_user) ? $status_by_hr_user->id : null
			);
		}
		
		// replace into the footprints database
		$footprints_tickets_mdl->replace(
			$ticket,
			$row->title,
			$priority,
			$contact_id,
			$submit_by_id,
			$row->submit_date,
			$row->update_date,
			$activity_id,
			$site_id,
			$lab_id,
			$type_id,
			$category_id,
			$sub_category_id,
			$eng_project_id,
			$weight,
			$status_id,
			$row->status_date,
			$status_by_id
		);
		$footprints_ticket_details_mdl->replace(
			$ticket,
			$row->descriptions,
			$row->all_descriptions,
			$row->resolutions,
			$row->agent_logs
		);
		
		// attach the assignees
		$assignee_ids = array();
		$assignees = str_replace("\n", " ", $row->assignees);
		$assignees = array_filter(array_map(array($this, 'normalizeUsername'), split(" ", $assignees)));
		
		foreach($assignees as $assignee) {
			$assignee_id = null;
			$assignee_user = $footprints_users_mdl->get(array(
				'username' => $assignee
			));
			
			if($assignee_user) {
				$assignee_id = $assignee_user->id;
			} else {
				$assignee_hr_user = $hr_users_mdl->get(array(
					'username' => $assignee,
					'domain_id' => $domain_id
				));
				
				$assignee_id = $footprints_users_mdl->insert(
					// username
					$assignee,
					// hr_user_id
					($assignee_hr_user) ? $assignee_hr_user->id : null
				);
			}
			
			$footprints_ticket_assignees_mdl->replace($ticket, $assignee_id);
			$assignee_ids[] = $assignee_id;
		}
		
		// delete all assignees that are not in the current list
		$delete = new Where();
		$delete->equalTo('ticket', $ticket);
		
		if(count($assignee_ids) > 0) {
			$delete->notIn('user_id', $assignee_ids);
		}
		
		$cnt = $footprints_ticket_assignees_mdl->delete($delete);
		
		// attach the infodots
		$infodots = str_replace("\n", " ", $row->infodots);
		$infodots = array_filter(array_map('trim', split(" ", $infodots)));
		foreach($infodots as $infodot) {
			$footprints_ticket_infodots_mdl->replace($ticket, $infodot);
		}
		
		// delete all infodots that are not in the current list
		$delete = new Where();
		$delete->equalTo('ticket', $ticket);
		
		if(count($infodots) > 0) {
			$delete->notIn('infodot', $infodots);
		}
		
		$cnt = $footprints_ticket_infodots_mdl->delete($delete);
		
		// attach all linked tickets
		$linked_tickets = array_filter(array_map('trim', split(' ', $row->linked_tasks)));
		foreach($linked_tickets as $lticket) {
			$sub_ticket = null;
			$type = null;
			
			if(preg_match("/^P([0-9]+)/", $lticket, $matches)) {
				$sub_ticket = $matches[1];
				$type = FootprintsTicketLinks::TYPE_PARENT;				
			} else if(preg_match("/^C([0-9]+)/", $lticket, $matches)) {
				$sub_ticket = $matches[1];
				$type = FootprintsTicketLinks::TYPE_CHILD;
			} else if(preg_match("/^L([0-9]+)[A-Z][0-9]+/", $lticket, $matches)) {
				$sub_ticket = $matches[1];
				$type = FootprintsTicketLinks::TYPE_DYNAMIC;
			} else {
				$sub_ticket = $lticket;
				$type = FootprintsTicketLinks::TYPE_STATIC;
			}
			
			$link = $footprints_ticket_links_mdl->get(array(
				'ticket' => $ticket,
				'sub_ticket' => $sub_ticket
			));
			
			if($link === false) {
				$footprints_ticket_links_mdl->insert($ticket, $sub_ticket, $type);
			} else {
				$footprints_ticket_links_mdl->update(array(
					'ticket' => $ticket,
					'sub_ticket' => $sub_ticket
				), array(
					'type' => $type
				));
			}
		}
	}
	
	public function cacheTickets($where, $offset = 0, $limit = null) {
		$db = $this->sm->get('db');
		$con = $db->getDriver()->getConnection();
		
		$rows = $this->dump($where, $offset, $limit);
		$commited = true;
		
		try {
			foreach($rows as $row) {
				$con->beginTransaction();
				$commited = false;
				
				if($this->hasLogger()) {
					$this->logger->log(Logger::INFO, "caching ticket {$row->ticket}");
				}
				
				$this->cacheTicket($row->ticket, $row);
				
				$con->commit();
				$commited = true;	
			}
		} catch(\Exception $e) {
			if($commited !== true) {
				$con->rollback();
			}
			
			// re-throw this error
			throw $e;
		}
	}
	
	protected function normalizeUsername($val) {
		$val = strtolower(trim($val));
		$val = self::decode($val);
		$val = array_shift(split('@', $val));
		$val = array_pop(split(':', $val));
		$val = array_shift(split('~', $val));
		$val = preg_replace("/_$/", "", $val);
		
		return $val;
	}
}
?>