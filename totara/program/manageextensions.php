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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage program
 */


require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

require_login();

$submitted = optional_param('submitbutton', null, PARAM_TEXT); //form submitted
$userid = optional_param('userid', 0, PARAM_INT);

if ((!empty($userid) && !totara_is_manager($userid, $USER->id)) && !is_siteadmin()) {
    print_error('nopermissions', 'error', '', get_string('manageextensions', 'totara_program'));
}

define('PROG_EXTENSION_GRANT', 1);
define('PROG_EXTENSION_DENY', 2);

if ($submitted && confirm_sesskey()) {
    $extensions = optional_param('extension', array(), PARAM_INT);

    if (!empty($extensions)) {
        $update_fail_count = 0;
        $update_extension_count = 0;

        foreach ($extensions as $id => $action) {
            if ($action == 0) {
                continue;
            }

            $update_extension_count++;

            if (!$extension = $DB->get_record('prog_extension', array('id' => $id))) {
                print_error('error:couldnotloadextension', 'totara_program');
            }

            if (!totara_is_manager($extension->userid)) {
                print_error('error:notusersmanager', 'totara_program');
            }

            if (!$roleid = $CFG->learnerroleid) {
                print_error('error:failedtofindstudentrole', 'totara_program');
            }

            if ($action == PROG_EXTENSION_DENY) {

                $userto = $DB->get_record('user', array('id' => $extension->userid));
                $userfrom = totara_get_manager($extension->userid);

                $messagedata = new stdClass();
                $messagedata->userto           = $userto;
                $messagedata->userfrom         = $userfrom;
                $messagedata->subject          = get_string('extensiondenied', 'totara_program');;
                $messagedata->contexturl       = $CFG->wwwroot.'/totara/program/required.php?id='.$extension->programid;
                $messagedata->contexturlname   = get_string('launchprogram', 'totara_program');
                $messagedata->fullmessage      = get_string('extensiondeniedmessage', 'totara_program');

                $eventdata = new stdClass();
                $eventdata->message = $messagedata;

                if ($result = tm_alert_send($messagedata)) {

                    $extension_todb = new stdClass();
                    $extension_todb->id = $extension->id;
                    $extension_todb->status = PROG_EXTENSION_DENY;

                    if (!$DB->update_record('prog_extension', $extension_todb)) {
                        $update_fail_count++;
                    }
                } else {
                    print_error('error:failedsendextensiondenyalert', 'totara_program');
                }
            } elseif ($action == PROG_EXTENSION_GRANT) {
                // Load the program for this extension
                $extension_program = new program($extension->programid);

                if ($prog_completion = $DB->get_record('prog_completion', array('programid' => $extension_program->id, 'userid' => $extension->userid, 'coursesetid' => 0))) {
                    $duedate = empty($prog_completion->timedue) ? 0 : $prog_completion->timedue;

                    if ($extension->extensiondate < $duedate) {
                        $update_fail_count++;
                        continue;
                    }
                }

                $now = time();
                if ($extension->extensiondate < $now) {
                    $update_fail_count++;
                    continue;
                }

                // Try to update due date for program using extension date
                if (!$extension_program->set_timedue($extension->userid, $extension->extensiondate)) {
                    $update_fail_count++;
                    continue;
                } else {
                    $userto = $DB->get_record('user', array('id' => $extension->userid));
                    if (!$userto) {
                        print_error('error:failedtofinduser', 'totara_program', $extension->userid);
                    }

                    $userfrom = totara_get_manager($extension->userid);

                    $messagedata = new stdClass();
                    $messagedata->userto           = $userto;
                    $messagedata->userfrom         = $userfrom;
                    $messagedata->subject          = get_string('extensiongranted', 'totara_program');
                    $messagedata->contexturl       = $CFG->wwwroot.'/totara/program/required.php?id='.$extension->programid;
                    $messagedata->contexturlname   = get_string('launchprogram', 'totara_program');
                    $messagedata->fullmessage      = get_string('extensiongrantedmessage', 'totara_program', userdate($extension->extensiondate, '%d/%m/%Y', $CFG->timezone));

                    if ($result = tm_alert_send($messagedata)) {

                        $extension_todb = new stdClass();
                        $extension_todb->id = $extension->id;
                        $extension_todb->status = PROG_EXTENSION_GRANT;

                        if (!$DB->update_record('prog_extension', $extension_todb)) {
                            $update_fail_count++;
                        }
                    } else {
                        print_error('error:failedsendextensiongrantalert', 'totara_program');
                    }
                }
            }
        }

        if ($update_extension_count == 0) {
            redirect('manageextensions.php');
        } elseif ($update_fail_count == $update_extension_count && $update_fail_count > 0) {
            totara_set_notification(get_string('updateextensionfailall', 'totara_program'), 'manageextensions.php?userid='.$userid);
        } elseif ($update_fail_count > 0) {
            totara_set_notification(get_string('updateextensionfailcount', 'totara_program', $update_fail_count), 'manageextensions.php?userid='.$userid);
        } else {
            totara_set_notification(get_string('updateextensionsuccess', 'totara_program'), 'manageextensions.php?userid='.$userid, array('class' => 'notifysuccess'));
        }
    }
}


$heading = get_string('manageextensions', 'totara_program');
$pagetitle = get_string('extensions', 'totara_program');

$PAGE->navbar->add($heading);
$PAGE->set_title($pagetitle);
$PAGE->set_heading('');
echo $OUTPUT->header();

if (!empty($userid)) {
    $backstr = "&laquo" . get_string('backtoallextrequests', 'totara_program');
    $url = new moodle_url('/totara/program/manageextensions.php');
    $link = html_writer::link($url, $backstr);
    echo html_writer::start_tag('p') . $link . html_writer::end_tag('p');
}

echo $OUTPUT->heading($heading);

if (!empty($userid)) {
    if (!$user = $DB->get_record('user', array('id' => $userid))) {
        print_error(get_string('error:invaliduser', 'totara_program'));
    }
    $user_fullname = fullname($user);

    $staff_ids = $userid;
} elseif ($staff_members = totara_get_staff()) {
    $staff_ids = implode(',', $staff_members);
}

if (!empty($staff_ids)) {
    list($staff_sql, $staff_params) = $DB->get_in_or_equal($staff_ids);
    $sql = "SELECT * FROM {prog_extension}
        WHERE status = 0
        AND userid {$staff_sql}";

    $extensions = $DB->get_records_sql($sql, $staff_params);

    if ($extensions) {

        $columns[] = 'user';
        $headers[] = get_string('name');
        $columns[] = 'program';
        $headers[] = get_string('program', 'totara_program');
        $columns[] = 'currentdate';
        $headers[] = get_string('currentduedate', 'totara_program');
        $columns[] = 'extensiondate';
        $headers[] = get_string('extensiondate', 'totara_program');
        $columns[] = 'reason';
        $headers[] = get_string('reason', 'totara_program');
        $columns[] = 'grant';
        $headers[] = get_string('grantdeny', 'totara_program');

        $table = new flexible_table('Extensions');
        $table->define_columns($columns);
        $table->define_headers($headers);

        $table->setup();

        $options = array(
            PROG_EXTENSION_GRANT => get_string('grant', 'totara_program'),
            PROG_EXTENSION_DENY => get_string('deny', 'totara_program'),
        );

        foreach ($extensions as $extension) {
            $tablerow = array();

            if ($prog_completion = $DB->get_record('prog_completion', array('programid' => $extension->programid, 'userid' => $extension->userid, 'coursesetid' => 0))) {
                $duedatestr = empty($prog_completion->timedue) ? get_string('duedatenotset', 'totara_program') : userdate($prog_completion->timedue, get_string('strftimedate', 'langconfig'), $CFG->timezone, false);
            }

            $prog_name = $DB->get_field('prog', 'fullname', array('id' => $extension->programid));
            $prog_name = empty($prog_name) ? '' : $prog_name;

            $user = $DB->get_record('user', array('id' => $extension->userid));
            $tablerow[] = fullname($user);
            $tablerow[] = $prog_name;
            $tablerow[] = $duedatestr;
            $tablerow[] = userdate($extension->extensiondate, get_string('strftimedate', 'langconfig'), $CFG->timezone, false);
            $tablerow[] = $extension->extensionreason;

            $pulldown_name = "extension[{$extension->id}]";
            $attributes = array();
            $attributes['disabled'] = false;
            $attributes['tabindex'] = 0;
            $attributes['multiple'] = false;
            $attributes['class'] = 'approval';
            $attributes['id'] = null;

            $pulldown_menu = html_writer::select($options, $pulldown_name, $extension->status, array(0 => 'choose'), $attributes);

            $tablerow[] = $pulldown_menu;
            $table->add_data($tablerow);
        }

        $currenturl = qualified_me();

        if (!empty($userid)) {
            echo html_writer::tag('p', get_string('viewinguserextrequests', 'totara_program', $user_fullname));
        }

        echo html_writer::start_tag('form', array('id'=>'program-extension-update', 'action'=>$currenturl, 'method'=>'POST'));
        echo html_writer::empty_tag('input', array('type'=>'hidden', 'id' => 'sesskey', 'name'=>'sesskey', 'value'=>sesskey()));
        $table->finish_html();
        echo html_writer::empty_tag('br');
        echo html_writer::empty_tag('input', array('type'=>'submit', 'name' => 'submitbutton', 'value' => get_string('updateextensions', 'totara_program')));
        html_writer::end_tag('form');

    } elseif (!empty($userid)) {
        echo html_writer::start_tag('p', get_string('nouserextensions', 'totara_program', $user_fullname));
    } else {
        echo html_writer::start_tag('p', get_string('noextensions', 'totara_program'));
    }
} else {
    echo html_writer::start_tag('p', get_string('notmanager', 'totara_program'));
}

echo $OUTPUT->footer();

?>