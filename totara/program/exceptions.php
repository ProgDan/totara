<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010-2012 Totara Learning Solutions LTD
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Ben Lobo <ben.lobo@kineo.com>
 * @package totara
 * @subpackage program
 */

/**
 * Program exceptions page
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('lib.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');

$id = required_param('id', PARAM_INT); // program id
$page = optional_param('page', 0, PARAM_INT);
$searchterm = optional_param('search', '', PARAM_TEXT);

admin_externalpage_setup('manageprograms', '', array('id' => $id), $CFG->wwwroot.'/totara/program/exceptions.php');

// Permissions check
$systemcontext = context_system::instance();

if (!has_capability('totara/program:handleexceptions', $systemcontext)) {
    print_error('error:nopermissions', 'totara_program');
}

// This session variable will be set to true following resolution of issues.
// This allows the page number to be reset (otherwise there is a chance that the
// page index will be wrong (as there are now less exceptions than before)
if (isset($_SESSION['exceptions_resolved']) && $_SESSION['exceptions_resolved']===true) {
    unset($_SESSION['exceptions_resolved']);
    $page = 0;
}

$program = new program($id);

$currenturl = qualified_me();
$currenturl_noquerystring = strip_querystring($currenturl);
$viewurl = $currenturl_noquerystring."?id={$id}&action=view";

$selectiontype = isset($_SESSION['exceptions_selectiontype']) ? $_SESSION['exceptions_selectiontype'] : SELECTIONTYPE_NONE;
$manually_added_exceptions = isset($_SESSION['exceptions_added']) ? $_SESSION['exceptions_added'] : array();
$manually_removed_exceptions = isset($_SESSION['exceptions_removed']) ? $_SESSION['exceptions_removed'] : array();

$exceptions = $program->get_exception_count();
$programexceptionsmanager = $program->get_exceptionsmanager();
$programexceptions = $programexceptionsmanager->search_exceptions($page, $searchterm);

$foundexceptionscount = $programexceptionsmanager->search_exceptions($page, $searchterm, '', true);
$programexceptionsmanager->set_selections($selectiontype, $searchterm);
$selected_exceptions = $programexceptionsmanager->get_selected_exceptions();

// Add the manually added selections to the global selection
$selected_exceptions = $selected_exceptions + $manually_added_exceptions;

// Remove the manually removed exceptions from the global selection
foreach ($manually_removed_exceptions as $id => $ex) {
    unset($selected_exceptions[$id]);
}

// Load jQuery and the dialogs
local_js(array(
    TOTARA_JS_DIALOG
));

// log this request
add_to_log(SITEID, 'program', 'view exceptions', "exceptions.php?id={$program->id}", $program->fullname);

///
/// Display
///
$category_breadcrumbs = get_category_breadcrumbs($program->category);

$heading = $program->fullname;
$pagetitle = format_string(get_string('program', 'totara_program').': '.$heading);
$navlinks = array();

foreach ($category_breadcrumbs as $node) {
    $PAGE->navbar->add_node($node);
}
$PAGE->navbar->add($program->shortname, $viewurl);
$PAGE->navbar->add(get_string('exceptionsreport', 'totara_program'));

echo $OUTPUT->header();

echo $OUTPUT->container_start('program exceptions', 'program-exceptions');

echo $OUTPUT->heading($heading.' - '.get_string('exceptionsreport', 'totara_program'));

$currenttab = 'exceptions';
require('tabs.php');

echo html_writer::start_tag('fieldset', array('id' => 'programexceptions'));
echo html_writer::start_tag('legend', array('class' => 'ftoggler')) . get_string('programexceptions', 'totara_program') . html_writer::end_tag('legend');
echo html_writer::start_tag('p') . get_string('instructions:programexceptions', 'totara_program') . html_writer::end_tag('p');

$renderer = $PAGE->get_renderer('totara_program');
echo $renderer->print_search($id, $searchterm);

$programexceptionsmanager->print_exceptions_form($id, $programexceptions, $selected_exceptions, $selectiontype);

$params = array('id' => $program->id);
if (!empty($searchterm)) {
    $params['search'] = $searchterm;
}

$base_url = new moodle_url('/totara/program/exceptions.php', $params);
$pagingbar = new paging_bar($foundexceptionscount, $page, RESULTS_PER_PAGE, $base_url);
$pagingbar->pagevar = $page;
echo $OUTPUT->render($pagingbar);

echo html_writer::end_tag('fieldset');

echo $OUTPUT->container_end();

$handledActions = $programexceptionsmanager->get_handled_actions_for_selection('json', $selected_exceptions);

// js requirements for page
$PAGE->requires->string_for_js('confirmresolution', 'totara_program');
$PAGE->requires->strings_for_js(array('ok', 'cancel'), 'moodle');
$args = array('args'=> '{"id":'.$id.','.
                        '"selected_exceptions_count":'.count($selected_exceptions).','.
                        '"handle_actions":'.$handledActions.','.
                        '"search_term":"'.$searchterm.'",'.
                        '"EXCEPTIONTYPE_TIME_ALLOWANCE":'.EXCEPTIONTYPE_TIME_ALLOWANCE.','.
                        '"EXCEPTIONTYPE_ALREADY_ASSIGNED":'.EXCEPTIONTYPE_ALREADY_ASSIGNED.','.
                        '"EXCEPTIONTYPE_COMPLETION_TIME_UNKNOWN":'.EXCEPTIONTYPE_COMPLETION_TIME_UNKNOWN.','.
                        '"SELECTIONTYPE_ALL":'.SELECTIONTYPE_ALL.','.
                        '"SELECTIONTYPE_NONE":'.SELECTIONTYPE_NONE.','.
                        '"SELECTIONTYPE_TIME_ALLOWANCE":'.SELECTIONTYPE_TIME_ALLOWANCE.','.
                        '"SELECTIONTYPE_ALREADY_ASSIGNED":'.SELECTIONTYPE_ALREADY_ASSIGNED.','.
                        '"SELECTIONTYPE_COMPLETION_TIME_UNKNOWN":'.SELECTIONTYPE_COMPLETION_TIME_UNKNOWN.'}');
$jsmodule = array(
     'name' => 'totara_programexceptions',
     'fullpath' => '/totara/program/exceptions.js',
     'requires' => array('json')
     );

$PAGE->requires->js_init_call('M.totara_programexceptions.init',$args, false, $jsmodule);

echo $OUTPUT->footer();

?>