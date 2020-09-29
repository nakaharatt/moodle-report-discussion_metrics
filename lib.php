<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     coursereport_discussion_metrics
 * @category    lib
 * @copyright   2020 Takahiro Nakahara <nakahara@3strings.co.jp>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

/**
 * This function extends the course navigation with the report items
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course to object for the report
 * @param stdClass $context The context of the course
 */
function report_discussion_metrics_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('report/discussion_metrics:view', $context)) {
        $url = new moodle_url('/report/discussion_metrics/index.php', array('id'=>$course->id));
        $navigation->add(get_string('pluginname', 'report_discussion_metrics'), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));
    }
}

/**
 * This function extends the course navigation with the report items
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $user
 * @param stdClass $course The course to object for the report
 */
/*
function report_discussion_metrics_extend_navigation_user($navigation, $user, $course) {
    if (report_discussion_metrics_can_access_user_report($user, $course)) {
        $url = new moodle_url('/report/discussion_metrics/user.php', array('id'=>$user->id, 'course'=>$course->id, 'mode'=>'discussion_metrics'));
        $navigation->add(get_string('discussion_metricsreport'), $url);
        $url = new moodle_url('/report/discussion_metrics/user.php', array('id'=>$user->id, 'course'=>$course->id, 'mode'=>'complete'));
        $navigation->add(get_string('completereport'), $url);
    }
}
*/
/**
 * Is current user allowed to access this report
 *
 * @private defined in lib.php for performance reasons
 *
 * @param stdClass $user
 * @param stdClass $course
 * @return bool
 */
function report_discussion_metrics_can_access_user_report($user, $course) {
    global $USER;

    $coursecontext = context_course::instance($course->id);
    $personalcontext = context_user::instance($user->id);

    if ($user->id == $USER->id) {
        if ($course->showreports and (is_viewing($coursecontext, $USER) or is_enrolled($coursecontext, $USER))) {
            return true;
        }
    } else if (has_capability('moodle/user:viewuseractivitiesreport', $personalcontext)) {
        if ($course->showreports and (is_viewing($coursecontext, $user) or is_enrolled($coursecontext, $user))) {
            return true;
        }

    }

    // Check if $USER shares group with $user (in case separated groups are enabled and 'moodle/site:accessallgroups' is disabled).
    if (!groups_user_groups_visible($course, $user->id)) {
        return false;
    }

    if (has_capability('report/discussion_metrics:viewuserreport', $coursecontext)) {
        return true;
    }

    return false;
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 * @return array
 */
function report_discussion_metrics_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $array = array(
        '*'                    => get_string('page-x', 'pagetype'),
        'report-*'             => get_string('page-report-x', 'pagetype'),
        'report-discussion_metrics-*'     => get_string('page-report-discussion_metrics-x',  'report_discussion_metrics'),
        'report-discussion_metrics-index' => get_string('page-report-discussion_metrics-index',  'report_discussion_metrics'),
        'report-discussion_metrics-user'  => get_string('page-report-discussion_metrics-user',  'report_discussion_metrics')
    );
    return $array;
}


/**
 * Add nodes to myprofile page.
 *
 * @param \core_user\output\myprofile\tree $tree Tree object
 * @param stdClass $user user object
 * @param bool $iscurrentuser
 * @param stdClass $course Course object
 *
 * @return bool
 */
/*
function report_discussion_metrics_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    if (empty($course)) {
        // We want to display these reports under the site context.
        $course = get_fast_modinfo(SITEID)->get_course();
    }
    if (report_discussion_metrics_can_access_user_report($user, $course)) {
        $url = new moodle_url('/report/discussion_metrics/user.php',
                array('id' => $user->id, 'course' => $course->id, 'mode' => 'discussion_metrics'));
        $node = new core_user\output\myprofile\node('reports', 'discussion_metrics', get_string('discussion_metricsreport'), null, $url);
        $tree->add_node($node);
        $url = new moodle_url('/report/discussion_metrics/user.php',
            array('id' => $user->id, 'course' => $course->id, 'mode' => 'complete'));
        $node = new core_user\output\myprofile\node('reports', 'complete', get_string('completereport'), null, $url);
        $tree->add_node($node);
    }
}
*/
