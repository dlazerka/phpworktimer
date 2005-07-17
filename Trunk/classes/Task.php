<?php
class Task {
	var $id;
	var $name;
	var $parentTaskId;
	var $total;
	var $cost;
	var $activeWorktimeId;
	var $worktimes;

	function Task($assocTask) {
		$this->id = $assocTask['id'];
		$this->name = $assocTask['name'];
		$this->total = isset($assocTask['total']) ? $assocTask['total'] : NULL;
		$this->parentTaskId = isset($assocTask['parent']) ? $assocTask['parent'] : NULL;
		$this->worktimes = array();
		$this->activeWorktimeId = NULL;
		
		$this->_CountCost();
	}

	function AddWorktime($worktime) {

		$this->worktimes[$worktime->id] = $worktime;

		if (!$worktime->stopTime) {
			if ($this->activeWorktimeId) {
				// we output error worktime anyway
				return 'Exception: already have active worktime';
			}
			$this->activeWorktimeId = $worktime->id;
		}
	}

	function Rename($name) {
		$name = trim($name);
		if (!$name) {
			return 'Exception: empty taskName';
		}

		$db = &$this->_getDb();
		$db->query("
			UPDATE task
			SET name = '". pg_escape_string($name) ."'
			WHERE id = $this->id
		");
		$this->Refresh();
	}

	function Start() {
		if ($this->activeWorktimeId) {
			return 'Exception: already started';
		}

		$db = &$this->_getDb();
		$db->query("
			INSERT INTO worktime(task, start_time)
			VALUES($this->id, 'now')
		");
		$rs = $db->query("
			SELECT *, stop_time - start_time AS duration
			FROM worktime
			WHERE id = currval('worktime_id_seq');
		");
		$worktime = new Worktime($db->fetch_assoc($rs));

		list($this->activeWorktimeId) = $worktime->id;

		$old_worktimes = $this->worktimes;
		$this->worktimes = array($worktime->id => $worktime);
		foreach ($old_worktimes as $worktime) {
			$this->worktimes[$worktime->id] = $worktime;
		}
	}

	function Stop() {
		if (!$this->activeWorktimeId) {
			return 'Exception: already stopped';
		}

		$result = $this->worktimes[$this->activeWorktimeId]->Stop();
		$this->activeWorktimeId = NULL;
		$this->Refresh();

		return $result;
	}

	function Delete() {
		$db = &$this->_getDb();
		$db->query("
			DELETE FROM task
			WHERE id = $this->id
		");
	}
	
	function Refresh() {
		$db = &$this->_getDb();
		$rs = $db->query("
			SELECT
				name,
				EXTRACT(day FROM SUM(stop_time - start_time))*24
					+ EXTRACT(hour FROM SUM(stop_time - start_time))
					|| TO_CHAR(SUM(stop_time - start_time), ':MI:SS') AS total
			FROM task
				LEFT JOIN worktime ON task.id = worktime.task
			WHERE task.id = $this->id
			GROUP BY task.id, name
		");
		list($this->name, $this->total) = $db->fetch_row($rs);
		
		$this->_CountCost();
	}

	/**
	 * Counts $this->cost and parses $this->total
	 */
	function _CountCost() {
		if ($this->total) {
			list($hours, $minutes, $seconds) = explode(':', $this->total);
	
			if ($seconds >= 30) {
				$minutes++;
				if ($minutes < 10) {
					$minutes = '0' . $minutes;
				}
				elseif ($minutes == 60) {
					$minutes = '00';
					$hours++;
				}
			}
			$this->total = (int)$hours . ':' . $minutes;
			$this->cost = round(5*($hours + $minutes/60), 2);
		}
		else {
			$this->cost = NULL;
		}
	}

	function &_getDb() {
		return new DB;
	}
}
?>