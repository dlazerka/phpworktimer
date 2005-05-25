<?php

if (!empty($_GET['parent']) && preg_match('/^[0-9]+$/', $_GET['parent'])) {
	$parent_id = $_GET['parent'];
}
else {
	$parent_id = NULL;
}


list($tasks, $timer_turned_on) = get_tasks($parent_id);
$path = get_path($parent_id);


$tpl = new Smarty;
$tpl->template_dir = ROOT_DIR;
$tpl->compile_dir = ROOT_DIR;
$tpl->assign('timer_turned_on', $timer_turned_on);
$tpl->assign('tasks', $tasks);
$tpl->assign('path', $path);
$tpl->display('show.tpl');


/** Returns path to the root of the tasks for task $parent_id */
function get_path($parent_id) {
	$path = array();
	while ($parent_id != NULL && count($path) <= 3) {
		$rs = pg_query("
			SELECT id, name, parent
			FROM task
			WHERE id = $parent_id
		");
		
		$task = pg_fetch_assoc($rs);
		$path[] = $task;
		$parent_id = $task['parent'];
	}
	$path = array_reverse($path);
	return $path;
}


/** Returns array with tasks with assigned 'worktimes' array. */
function get_tasks($parent_id) {

	$timer_turned_on = false;
	
	$rs = pg_query("
		SELECT *
		FROM task
		WHERE parent ".($parent_id ? " = $parent_id" : "IS NULL")."
		ORDER BY id DESC
	"); 
	$tasks = array();
	while ($task = pg_fetch_assoc($rs)) {
		$tasks[$task['id']] = $task;
		$tasks[$task['id']]['worktimes'] = array();
		$tasks[$task['id']]['is_working_on'] = false;
	}
	
	$rs = pg_query("
		SELECT *, worktime.id AS id
		FROM worktime
			INNER JOIN task ON task.id = worktime.task
		WHERE parent ".($parent_id ? " = $parent_id" : "IS NULL")."
		ORDER BY end_time
	"); 
	while ($worktime = pg_fetch_assoc($rs)) {
		
		$tasks[$worktime['task']]['worktimes'][] = $worktime;
		
		if (!$worktime['end_time']) {
			$tasks[$worktime['task']]['is_working_on'] = true;
			$timer_turned_on = true;
		}
	}
	
	return array($tasks, $timer_turned_on);
}
?>