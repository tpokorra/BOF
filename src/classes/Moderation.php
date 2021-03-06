<?php

namespace ICCM\BOF;
use \Firebase\JWT\JWT;
use \PDO;

class Moderation
{
	private $view;
	private $db;
	private $router;

	function __construct($view, $db, $router) {
		$this->view = $view;
		$this->db = $db;
		$this->router = $router;
	}

	public function showModerationView($request, $response, $args) {
		$is_admin = $request->getAttribute('is_admin');
		if (!$is_admin) die("you don't have permissions for this page");

		$sql = "SELECT *, '' as leader FROM `workshop`";
		$query=$this->db->prepare($sql);
		$param = array ();
		$query->execute($param);
		$bofs = array ();
		while ($row=$query->fetch(PDO::FETCH_OBJ)) {
			$bofs [] = $row;
		}

		$sql = 'SELECT p.`name`, wp.`workshop_id`, wp.`leader`, wp.`participant` FROM `workshop_participant` wp, `participant` p WHERE wp.`participant_id` = p.`id`';
		$query=$this->db->prepare($sql);
		$param = array ();
		$query->execute($param);
		while ($row=$query->fetch(PDO::FETCH_OBJ)) {
			foreach ($bofs as $bof) {
				if ($bof->id == $row->workshop_id) {
					if ($row->participant == 1) {
						if ($bof->fullvoters != "") {
							$bof->fullvoters .= ', ';
						}
						$bof->fullvoters .= $row->name;
					}
					if ($row->leader == 1) {
						if ($bof->leader != "") {
							$bof->leader .= ', ';
						}
						$bof->leader .= $row->name;
					}
				}
			}
		}

		$sql = 'SELECT p.`name`, w.`id` as workshop_id FROM `workshop` w, `participant` p WHERE w.`creator_id` = p.`id`';
		$query=$this->db->prepare($sql);
		$param = array ();
		$query->execute($param);
		while ($row=$query->fetch(PDO::FETCH_OBJ)) {
			foreach ($bofs as $bof) {
				if ($bof->id == $row->workshop_id) {
						$bof->createdby = $row->name;
				}
			}
		}

		$sql = 'SELECT * FROM `participant` WHERE `name` <> "admin"';
		$query=$this->db->prepare($sql);
		$param = array ();
		$query->execute($param);
		$participants = array ();
		while ($row=$query->fetch(PDO::FETCH_OBJ)) {
			$participants [] = $row;
		}

		return $this->view->render($response, 'moderation.html',[
			'bofs' => $bofs,
			'participants' => $participants
			]);
	}

	public function moderate($request, $response, $args) {
		$is_admin = $request->getAttribute('is_admin');
		if (!$is_admin) die("you don't have permissions for this page");

		$data = $request->getParsedBody();
		$operation = $data['operation'];
		
		if ($operation == "delete") {
			return $this->moderateDelete($request, $response, $args);
		}
		else if ($operation == "merge") {
			return $this->moderateMerge($request, $response, $args);
		} 
		else if ($operation == "addFacilitator") {
			return $this->moderateAddFacilitator($request, $response, $args);
		} 
		else {
			return $this->moderateUpdate($request, $response, $args);
		}
	}
	
	public function moderateUpdate($request, $response, $args) {
		$is_admin = $request->getAttribute('is_admin');
		if (!$is_admin) die("you don't have permissions for this page");

		$data = $request->getParsedBody();
		$title = $data['title'];
		$description = $data['description'];
		$id = $data['id'];
		if (empty($data['published'])) {
			$published = 0;
		} 
		else {
			$published = 1;
		}
		$sql = 'UPDATE `workshop`
				SET `name` = ?, `description` = ?, `published` = ?
				WHERE `id` = ?';
		
		$query=$this->db->prepare($sql);
		$param = array ($title, $description, $published, $id);
		
		$query->execute($param);
		
		return $this->showModerationView($request, $response, $args);
	}
	public function moderateAddFacilitator($request, $response, $args) {
		$is_admin = $request->getAttribute('is_admin');
		if (!$is_admin) die("you don't have permissions for this page");

		$data = $request->getParsedBody();
		$facilitator = $data['facilitator'];
		$id = $data['id'];
		
	
		$sql = 'INSERT INTO `workshop_participant` (`workshop_id`,`participant_id`,`leader`) 
				VALUES (?,?,1)
				ON DUPLICATE KEY UPDATE `leader` = 1';
				
		
		$query = $this->db->prepare($sql);
		$param = array ($id, $facilitator);
		
		$query->execute($param);
		
		return $this->showModerationView($request, $response, $args);
	}
	
	public function moderateMerge($request, $response, $args) {
		$is_admin = $request->getAttribute('is_admin');
		if (!$is_admin) die("you don't have permissions for this page");

		$data = $request->getParsedBody();
		$id = $data['id'];
		$mergeWithId = $data['mergeWithWorkshop'];		
		
		// Get current row
		$sql = 'SELECT * FROM `workshop` WHERE `id` = ?';
		$query=$this->db->prepare($sql);
		$param = array ($id);
		$query->execute($param);
		$row = $query->fetch(PDO::FETCH_ASSOC);
		
		$origName = $row['name'];
		$origDescription = $row['description'];
		
		// Get selected row
		$sql = 'SELECT * FROM `workshop` WHERE `id` = ?';
		$query=$this->db->prepare($sql);
		$param = array ($mergeWithId);
		$query->execute($param);
		$row = $query->fetch(PDO::FETCH_ASSOC);
		
		$secondName = $row['name'];
		$secondDescription = $row['description'];
		
		// Merge selected row into current row
		
		$mergeName = $origName . " and " . $secondName;
		$mergeDescription = $origDescription . " and " . $secondDescription;
		
		$sql = 'UPDATE `workshop`
				SET `name` = ?, `description` = ?
				WHERE `id` = ?';

		$query = $this->db->prepare($sql);
		$param = array ($mergeName, $mergeDescription, $id);
		
		$query->execute($param);
		
		// Delete selected row
		
		$sql = 'DELETE FROM `workshop`
				WHERE `id` = ?';
		
		$query=$this->db->prepare($sql);
		$param = array ($mergeWithId);
		
		$query->execute($param);

		return $this->showModerationView($request, $response, $args);
	}

	public function moderateDelete($request, $response, $args) {
		$is_admin = $request->getAttribute('is_admin');
		if (!$is_admin) die("you don't have permissions for this page");

		$data = $request->getParsedBody();
		$id = $data['id'];
		
		$sql = 'DELETE FROM `workshop`
				WHERE `id` = ?';
		
		$query=$this->db->prepare($sql);
		$param = array ($id);
		
		$query->execute($param);
		
		return $this->showModerationView($request, $response, $args);
	}


}

?>
