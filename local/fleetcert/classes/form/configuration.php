<?php
namespace local_fleetcert\form;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
final class configuration extends \moodleform {
 public function definition() {
  $m = $this->_form;
  $m->addElement('text', 'name', 'New organization name'); $m->setType('name', PARAM_TEXT);
  $m->addElement('text', 'courseid', 'Required Moodle course ID (existing organization)'); $m->setType('courseid', PARAM_INT);
  $m->addElement('text', 'validdays', 'Validity in days'); $m->setType('validdays', PARAM_INT); $m->setDefault('validdays', 365);
  $m->addElement('text', 'reminddays', 'Reminder window in days'); $m->setType('reminddays', PARAM_INT); $m->setDefault('reminddays', 30);
  $this->add_action_buttons(false, 'Save configuration');
 }
 public function validation($data, $files) {
  $errors = parent::validation($data, $files);
  if (empty($data['name']) && empty($data['courseid'])) { $errors['name'] = 'Enter an organization name or course ID'; }
  if (!empty($data['courseid']) && ($data['validdays'] < 1 || $data['reminddays'] < 0 || $data['reminddays'] >= $data['validdays'])) {
   $errors['reminddays'] = 'Use 0 <= reminder days < validity days';
  }
  return $errors;
 }
}
