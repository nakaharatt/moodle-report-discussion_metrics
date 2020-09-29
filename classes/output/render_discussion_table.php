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
 * Plugin event classes are defined here.
 *
 * @package     coursereport_discussion_metrics
 * @copyright   2020 Takahiro Nakahara <nakahara@3strings.co.jp>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_discussion_metrics\output;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir.'/tablelib.php');
/**
 * The viewed event class.
 *
 * @package    coursereport_discussion_metrics
 * @copyright  2020 Takahiro Nakahara <nakahara@3strings.co.jp>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class render_discussion_table{

    public function render($data){
        $table = new flexible_table('forum_report_table');
        $table->define_baseurl($PAGE->url);
        $table->define_columns(array('forum','discussion','posts', 'replies','replytime','density','wordcount', 'views','multimedia','participants','multinational'));
        $table->define_headers(array("Forum",'Discussion',$strposts,$strreplies,'Reply Time',"Density",$strwordcount,$strviews,$strmultimedia,'Participants','Multinational',$strfp,$strlp,''));
        $table->sortable(true);
        $table->collapsible(true);
        $table->set_attribute('class', 'admintable generaltable');
        $table->setup();
        $sortby = $table->get_sort_columns();
        if($sortby){
            $orderby = array_keys($sortby)[0];
            $ascdesc = ($sortby[$orderby] == 4) ?'ASC':'DESC';
            if(strpos($orderby,'name') !== FALSE){
                $orderbyname = $orderby.' '.$ascdesc;
            }else{
                $orderbyname = '';
            }
        }else{
            $orderbyname = '';
        }
        if($sortby && !$orderbyname){
            usort($data,forum_report_sort($sortby));
        }
        foreach($data as $row){
            //$table->data[] = array($row->name,$row->country,$row->posts,$row->replies,$row->wordcount,$row->views,$row->firstpost,$row->lastpost,$sendreminder,$complink);
            $trdata = array($row->forumname,$row->name,$row->posts,$row->replies,$row->replytime,$row->density,$row->wordcount,$row->views,$row->multimedia,$row->participants,$row->multinationals,$row->firstpost,$row->lastpost);
            $table->add_data($trdata);
        }
        echo '<input type="hidden" name="course" id="courseid" value="'.$courseid.'">';
        if($forumid){
            echo '<input type="hidden" name="forum" id="forumid" value="'.$forumid.'">';
        }
        $table->finish_output();
    }

}
