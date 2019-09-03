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
 * @author  Erlend Strømsvik - Ny Media AS
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package auth/saml
 *
 * Authentication Plugin: SAML based SSO Authentication
 *
 * Authentication using SAML2 with SimpleSAMLphp.
 *
 * Based on plugins made by Sergio Gómez (moodle_ssp) and Martin Dougiamas (Shibboleth).
 */

define('CLI_SCRIPT', true);

// Global moodle config file.
require(dirname(dirname(dirname(__DIR__))).'/config.php');
global $CFG;

require_once("$CFG->libdir/clilib.php");

list($options, $unrecognized) = cli_get_params(
    ['filename' => 'php://stdin', 'mode' => 'add', 'help' => false],
    ['f' => 'file', 'm'=> 'mode', 'h' => 'help']
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

set_debugging(DEBUG_DEVELOPER, true);

if ($options['help']) {
    $help = "Execute course mapping sync for the SAML plugin.

Options:
-f, --filename        Name of the CSV file that contains course mapping
-m, --mode            Mode. Replace or add the mapping values from the plugin
                      with the values on the csv.
                      Options: add|replace   Default: add
-h, --help            Print out this help
";
    echo $help;
    die;
}

if (!empty($options['filename'])) {
    $filename = $options['filename'];
}

if ($filename <> 'php://stdin' && !file_exists($filename)) {
    print($filename." file not found");
    die;
}

if (!empty($options['mode'])) {
    if (!in_array($options['mode'], ['add', 'replace'])) {
        print "Invalid mode, valid options are:  add | replace";
        die;
    }
    $mode = $options['mode'];
}

$f = fopen($filename, "r");
if ($f === false) {
    print("Error reading ".$filename);
    exit();
}

$enrolpluginconfig = get_config('enrol_saml');
$prefixes = $enrolpluginconfig->group_prefix;

if (!empty($prefixes)) {
        list($prefix) = explode(",",$prefixes);
}else
        $prefix = 'UXXI_';

$mapping = [];
while (($data = fgetcsv($f, 1000, ";")) !== false) {

    $moodlecourse = $data[0];

    list($course, $group) = explode("-",$data[1]);

    if (!empty($group))
        #$idpcourse = $course . '-' . $prefix . $group;
        $idpcourse = $course . '-' . $prefix . $data[1];
    else
        $idpcourse = $course;

    if (array_key_exists($moodlecourse, $mapping)) {
        $mapping[$moodlecourse][] = $idpcourse;
    } else {
        $mapping[$moodlecourse] = [$idpcourse];
    }
}
fclose($f);

require_once($CFG->dirroot.'/auth/saml/locallib.php');

$pluginconfig = get_config('auth_saml');
$coursemapping = get_course_mapping_for_sync($pluginconfig);

foreach ($mapping as $moodlecourse => $idpcourses) {

    $values = array_unique(clean_values($idpcourses));
    if ($mode == 'add') {
        if (isset($coursemapping[$moodlecourse])) {
            $values = array_unique(array_merge($values, clean_values($coursemapping[$moodlecourse])));
        }
    }
    $cleanmoodlecoursename = convert_to_valid_setting_name($moodlecourse);
    if (!empty($cleanmoodlecoursename)) {
        set_config('course_mapping_'.$cleanmoodlecoursename, implode(",", $values), 'auth_saml');
       print "$cleanmoodlecoursename =>" . implode(",", $values) . "\n";
    }
}
