<?php
namespace Ticket\Footprints;

class Api {
	protected $host;
	protected $project_id;
	protected $username;
	protected $password;
	
	protected $error;
	
	const STATIC_LINK = 'static';
	const DYNAMIC_LINK = 'dynamic';
	
	public function __construct($host, $project_id, $username, $password) {
		$this->host = $host;
		$this->project_id = $project_id;
		$this->username = $username;
		$this->password = $password;
	}
	
	public static function factory($sm) {
		$config = $sm->get('Config');
		return new self(
			$config['footprints']['host'],
			$config['footprints']['project_id'],
			$config['footprints']['username'],
			$config['footprints']['password']
		);
	}
	
	public function getError() {
		return $this->error;
	}
	
	public function getClient() {
		return new \SoapClient(NULL, array(
			'location' => "https://{$this->host}/MRcgi/MRWebServices.pl",
			'uri' => 'MRWebServices',
			'style' => SOAP_RPC,
			'use' => SOAP_ENCODED
		));
	}
	
	public static function decode($str) {
		$str = str_replace('__b', ' ', $str);
		$str = str_replace('__B', ' ', $str);
		$str = str_replace('__P', '(', $str);
		$str = str_replace('__p', ')', $str);
		$str = str_replace('__u', '-', $str);
		$str = str_replace('__U', '-', $str);
		
		$str = html_entity_decode($str, ENT_QUOTES, "ISO-8859-1");
		$str = preg_replace('/&#(\d+);/me',"chr(\\1)", $str);
		$str = preg_replace('/&#x([a-f0-9]+);/mei',"chr(0x\\1)", $str);
		
		return $str;
	}
	
	public static function encode($str) {
		$str = str_replace(' ', '__b', $str);
		$str = str_replace('(', '__P', $str);
		$str = str_replace(')', '__p', $str);
		$str = str_replace('-', '__u', $str);
		
		$str = htmlentities($str, ENT_QUOTES, "ISO-8859-1");
		
		return $str;
	}
	
	public static function encodeArray($arr) {
		foreach($arr as $field => &$val) {
			$val = self::encode($val);
		}
		return $arr;
	}
	
	public static function encodeData($data) {
		// these are fields we should not encode
		$ignore_fields = array(
			'title',
			'description',
			'selectContact'
		);
		
		foreach($data as $field => $info) {
			if(in_array($field, $ignore_fields)) {
				continue;
			} else if(is_array($info)) {
				$new_info = array();
				
				foreach($info as $f => $v) {
					if(is_numeric($f)) {
						$new_info[$f] = self::encode($v);
					} else {
						$new_info[self::encode($f)] = self::encode($v);
					}
				}
				
				$data[$field] = $new_info;
			} else {
				$data[$field] = self::encode($info);
			}
		}
		
		return $data;
	}
	
	public function getStatus($ticket) {
		$row = $this->dump(array("M.MRID = '{$ticket}'"));
		if(is_array($row)) {
			$row = array_shift($row);
		}
		
		if(empty($row)) {
			return false;
		}
		
		return self::decode($row->status);		
	}
	
	public function edit($ticket, $submitter, $status, $data) {
		$data['projectID'] = $this->project_id;
		$data['mrID'] = $ticket;
		if(!empty($status)) {
			$data['status'] = $status;
		}
		if(!empty($submitter)) {
			$data['submitter'] = $submitter;
		}
		
		try {
			$client = $this->getClient();
			$client->MRWebServices__editIssue($this->username, $this->password, '', $data);
			return true;
		} catch(\Exception $e) {
			$this->error = $e->getMessage();
			return false;
		}
	}
	
	public function updateReceive($ticket, $submitter, $description, $data = array()) {
		$data = array_merge($data, array(
			'description' => $description,
			'mail' => array(
				'assignees' => 1
			)
		));
		return $this->edit($ticket, $submitter, 'Update Received', $data);
	}
	
	public function pendingCustomer($ticket, $submitter, $description, $data = array()) {
		$data = array_merge($data, array(
			'description' => $description,
			'mail' => array(
				'contact' => 1,
				'permanentCCs' => 1
			)
		));
		return $this->edit($ticket, $submitter, 'Pending - Customer', $data);
	}
	
	public function wip($ticket, $submitter, $description = 'WIP', $data = array()) {
		$data = array_merge($data, array(
			'description' => $description,
			'mail' => array(
				'contact' => 1,
				'permanentCCs' => 1
			)
		));
		return $this->edit($ticket, $submitter, 'WIP', $data);
	}
	
	public function resolved($ticket, $submitter, $resolution, $alertAssignees = false, $data = array()) {
		$mail = array(
			'contact' => 1,
			'permanentCCs' => 1
		);
		
		if($alertAssignees) {
			$mail['assignees'] = 1;
		}
		
		$data = array_merge($data, array(
			'description' => $resolution,
			'mail' => $mail,
			'projfields' => array(
				'Resolution' => $resolution
			)
		));
		
		return $this->edit($ticket, $submitter, 'Resolved', $data);
	}
	
	public function create($submitter,
						   $contact,
						   $title,
						   $description,
						   $site,
						   $lab,
						   $category,
						   $activity,
						   $subcategory,
						   $eng_project = 'No Choice',
						   $weight = 1,
						   $priority = 4,
						   $status = 'Open',
						   $assignees = array(),
						   $ccs = array(),
						   $silent = false,
						   $projfields = array()) {
		$mail = array(
			'assignees' => 1,
			'contact' => 1,
			'permanentCCs' => 1
		);
		if($silent === true) {
			$mail = array(
				'assignees' => 0
			);
		}
		
		$data = array(
			'projectID' => $this->project_id,
			'title' => $title,
			'description' => $description,
			'priorityNumber' => $priority,
			'mail' => $mail
		);
		
		if(!empty($submitter)) {
			$data['submitter'] = $submitter;
		}
		
		if(!empty($status)) {
			if(!in_array($status, array('Open', 'Assigned', 'Scheduled'))) {
				throw new \Exception("invalid status given");
			}
			$data['status'] = $status;
		} else {
			$data['status'] = 'Open';
		}
		
		if(!empty($assignees) && is_array($assignees)) {
			$data['assignees'] = array_values($assignees);
		} else if(!empty($assignees)) {
			$data['assignees'] = array($assignees);
		}
		
		if(!empty($ccs) && is_array($ccs)) {
			$data['permanentCCs'] = array_values($ccs);
		} else if(!empty($ccs)) {
			$data['permanentCCs'] = array($ccs);
		}
		
		$data['projfields'] = array(
			'Type' => 'Engineering Lab Services'
		);
		
		if(!empty($site)) {
			$data['projfields']['Site'] = $site;
		}
		if(!empty($lab)) {
			$data['projfields']['Lab'] = $lab;
		}
		if(!empty($category)) {
			$data['projfields']['Category'] = $category;
		}
		if(!empty($activity)) {
			$data['projfields']['Activity'] = $activity;
		}
		if(!empty($subcategory)) {
			$data['projfields']['Sub Category'] = $subcategory;
		}
		if(!empty($eng_project)) {
			$data['projfields']['Engineering Project'] = $eng_project;
		}
		if(!empty($projfields) && is_array($projfields)) {
			$data['projfields'] = array_merge($data['projfields'], $projfields);
		}
		
		if($weight > 15) {
			if($weight % 5 != 0) {
				throw new \Exception("weight over 15 must be in increments of 5 up to max of 105");
			}
			$data['projfields']['Weight'] = 'Heavy';
			$data['projfields']['Heavyweight'] = $weight;
		} else {
			$data['projfields']['Weight'] = $weight;
		}
		
		if(!empty($contact)) {
			if(is_string($contact)) {
				$contact = array(
					'username' => $contact,
					'email' => "{$contact}@brocade.com"
				);
			}
			$data['abfields'] = array();
			
			if(!empty($contact['username'])) {
				$data['abfields']['User ID'] = $contact['username'];
			}
			if(!empty($contact['email'])) {
				$data['abfields']['Email Address'] = $contact['email'];
			}			
			if(!empty($contact['first_name'])) {
				$data['abfields']['First Name'] = $contact['first_name'];
			}
			if(!empty($contact['last_name'])) {
				$data['abfields']['Last Name'] = $contact['last_name'];
			}
		}
		
		$data = self::encodeData($data);
		
		$client = $this->getClient();
		return $client->MRWebServices__createIssue($this->username, $this->password, '', $data);
	}
	
	public function createSubtask($ticket,
								  $submitter,
								  $contact,
								  $title,
								  $description,
								  $site,
								  $lab,
								  $category,
								  $activity,
								  $subcategory,
								  $eng_project = 'No Choice',
								  $weight = 1,
								  $priority = 4,
								  $status = 'Open',
								  $assignees = array(),
								  $ccs = array(),
								  $silent = false,
								  $projfields = array()) {
		$new_ticket = $this->create($submitter,
								    $contact,
								    $title,
								    $description,
								    $site,
								    $lab,
								    $category,
								    $activity,
								    $subcategory,
								    $eng_project,
								    $weight,
								    $priority,
								    $status,
								    $assignees,
								    $ccs,
								    $silent,
								    $projfields);
		if(!empty($new_ticket)) {
			$this->link($ticket, $new_ticket);
		}
		
		return $new_ticket;
	}
	
	public function link($ticket1, $ticket2, $linkType = self::STATIC_LINK) {
		$data = array(
			'issue1' => array(
				'projectID' => $this->project_id,
				'mrID' => $ticket1
			),
			'issue2' => array(
				'projectID' => $this->project_id,
				'mrID' => $ticket2
			),
			'linkType' => $linkType
		);
		
		$client = $this->getClient();
		return $client->MRWebServices__linkIssues($this->username, $this->password, '', $data);
	}
	
	public function query($query) {
		return $this->getClient()->MRWebServices__search($this->username, $this->password , '', $query);
	}
	
	public function total($where) {
		$where = $this->where($where);
		$query = "
			SELECT
				COUNT(*) AS cnt
			FROM MASTER{$this->project_id} M
				INNER JOIN MASTER{$this->project_id}_STATUS S ON M.MRID = S.MRID
				INNER JOIN MASTER{$this->project_id}_ABDATA AB ON M.MRID = AB.MRID
			{$where}
		";
		
		$rows = $this->query($query);
		if(is_array($rows) && count($rows) > 0) {
			foreach($rows as $row) {
				return intval($row->cnt);
			}
		}
		
		return 0;
	}
	
	public function dump($where, $offset = 0, $limit = null) {
		$where = $this->where($where);
		$start = $offset;
		if($limit !== null) {
			$end = $offset + $limit;
		} else {
			// we need to grab the total
			$end = $this->total($where);			
		}
		
		$query = "
			SELECT
				*
			FROM (
				SELECT
					M.MRID AS TICKET,
					M.MRTITLE AS TITLE,
					M.MRDESCRIPTION AS DESCRIPTIONS,
					M.MRALLDESCRIPTIONS AS ALL_DESCRIPTIONS,
					M.RESOLUTION AS RESOLUTIONS,
					M.AGENT__BLOG AS AGENT_LOGS,
					M.MRPRIORITY AS PRIORITY,
					AB.USER__BID AS CONTACT,
					M.MRSUBMITTER AS SUBMIT_BY,
					M.MRSUBMITDATE AS SUBMIT_DATE,
					M.MRUPDATEDATE AS UPDATE_DATE,
					M.ACTIVITY,
					M.SITE,
					M.LAB,
					M.TYPE,
					M.CATEGORY,
					M.SUB__BCATEGORY AS SUB_CATEGORY,
					M.ENGINEERING__BPROJECT AS ENG_PROJECT,
					M.WEIGHT,
					M.HEAVYWEIGHT,
					S.MRNEWSTATUS AS STATUS,
					S.MRTIMESTAMP AS STATUS_DATE,
					S.MRUSERID AS STATUS_BY,
					M.EQUIPMENT__BINFODOT__B__PS__P AS INFODOTS,
					M.MRASSIGNEES AS ASSIGNEES,
					M.MRREF_TO_MR AS LINKED_TASKS,
					ROWNUM AS RNUM
				FROM MASTER{$this->project_id} M
					INNER JOIN MASTER{$this->project_id}_STATUS S ON M.MRID = S.MRID
					INNER JOIN MASTER{$this->project_id}_ABDATA AB ON M.MRID = AB.MRID
				{$where}
				ORDER BY M.MRUPDATEDATE ASC
			)
			WHERE RNUM >= {$start} AND RNUM <= {$end}
		";
		
		return $this->query($query);
	}
	
	protected function where($where) {
		if(is_numeric($where)) {
			// we will assume a ticket is passed
			$where = array("M.MRID = '{$where}'");
		}
		
		$sql_where = "";
		$and = 'WHERE';
		
		foreach($where as $value) {
			$sql_where .= " {$and} {$value} ";
			$and = "AND";
		}
		
		return $sql_where;
	}
}
?>