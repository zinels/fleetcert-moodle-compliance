<?php
namespace local_fleetcert\form;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
final class upload extends \moodleform {
 public function definition() {
  $this->_form->addElement('filepicker', 'employees', 'Employee CSV', null, ['accepted_types' => ['.csv'], 'maxbytes' => 1048576]);
  $this->_form->addRule('employees', null, 'required');
  $this->add_action_buttons(false, 'Validate and preview');
 }
}
