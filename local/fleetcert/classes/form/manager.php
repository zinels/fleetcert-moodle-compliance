<?php
namespace local_fleetcert\form;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

final class manager extends \moodleform {
    public function definition() {
        $form = $this->_form;
        $form->addElement('text', 'userid', 'Moodle user ID');
        $form->setType('userid', PARAM_INT);
        $form->addRule('userid', 'Enter a user ID', 'required', null, 'client');
        $form->addElement('select', 'operation', 'Action', ['assign' => 'Assign manager', 'remove' => 'Remove assignment']);
        $this->add_action_buttons(true, 'Save assignment');
    }
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($data['userid'] < 1) {
            $errors['userid'] = 'Enter a positive Moodle user ID';
        }
        if (!in_array($data['operation'], ['assign', 'remove'], true)) {
            $errors['operation'] = 'Choose a valid action';
        }
        return $errors;
    }
}
