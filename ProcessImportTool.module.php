<?php namespace ProcessWire;

class ProcessImportTool extends Process implements Module {

	public function ___execute() {
		if ($this->input->post('submit')) {
			$this->processForm();
		}
		return $this->getForm()->render();
	}

	protected function processForm() {

		$form = $this->getForm();
		$form->processInput($this->input->post);
		if (count($form->getErrors())) {
			$this->error($form->getErrors());
			return false;
		}

		/** @var Pagefiles */
		$import_file = $form->getChildByName('import_file')->value;
		if (!count($import_file)) {
			$this->error($this->_('Missing required import file'));
			return false;
		}

		$config = $this->config->ImportTool;
		$import_profile_name = $form->getChildByName('import_profile')->value;
		$import_profile = $config['profiles'][$import_profile_name] ?? null;
		if (empty($import_profile)) {
			$this->error($this->_('Missing required import profile'));
			return false;
		}

		$this->session->setFor('ImportTool', 'import_profile_name', $import_profile_name);

		$import_file_name = 'import-' . time() . '-' . $this->user->id . '.csv';
		$import_file_path = $this->getProcessPage()->filesManager()->path();

		$import_file = $import_file->first();
		$import_file->rename($import_file_path . $import_file_name);
		$import_file = $import_file->filename;

		if (is_file($import_file)) {
			/** @var ImportTool */
			$importTool = $this->modules->get('ImportTool');
			$importTool->setProfile($import_profile_name);
			if ($count = $importTool->importFromFile($import_file)) {
				$this->session->message(implode(', ', array_filter([
					sprintf($this->_('%d rows processed'), $count['row_num']),
					empty($count['imported']) ? null : sprintf($this->_('%d pages imported'), $count['imported']),
					empty($count['updated']) ? null : sprintf($this->_('%d pages updated'), $count['updated']),
				])));
				$this->session->redirect($this->page->url, false);
			}
		}
	}

	protected function getForm(): InputfieldForm {

		/** @var InputfieldForm $form */
		$form = $this->modules->get('InputfieldForm');

		/** @var InputfieldSelect */
		$import_profile = $this->modules->get('InputfieldSelect');
		$import_profile->name = 'import_profile';
		$import_profile->label = $this->_('Import profile');
		$import_profile->description = implode(' ', [
			$this->_('Import profile defines the exact process used for importing data from import file.'),
			$this->_('Be sure to select an import profile designed specifically for the file you want to import.'),
		]);
		$import_profile->notes = $this->_('Once you select a profile, any additional notes it may have will appear here.');
		$import_profile->required = true;
		$import_profile->attr('required', true);
		$import_profile->attr('onchange', <<<JAVASCRIPT
var notes = this.nextElementSibling
notes.style.wordBreak = 'break-all'
if (!notes.getAttribute('data-notes')) notes.setAttribute('data-notes', notes.innerText)
notes.innerText = this.selectedIndex ? this.options[this.selectedIndex].getAttribute('data-notes') : notes.getAttribute('data-notes')
JAVASCRIPT);
		if (count($this->config->ImportTool['profiles'])) {
			foreach ($this->config->ImportTool['profiles'] as $profile_name => $profile_data) {
				$import_profile->addOption($profile_name, $profile_data['label'] ?? $profile_name, [
					'data-notes' => $profile_data['notes'] ?? '',
				]);
			}
		}
		$form->add($import_profile);

		$import_profile_name = $this->session->getfor('ImportTool', 'import_profile_name');
		if ($import_profile_name) {
			$import_profile->value = $import_profile_name;
		}

		/** @var InputfieldFile */
		$import_file = $this->modules->get('InputfieldFile');
		$import_file->name = 'import_file';
		$import_file->label = $this->_('Import file');
		$import_file->icon = 'file-text';
		$import_file->extensions = 'csv txt';
		$import_file->maxFiles = 1;
		$import_file->descriptionRows = 0;
		$import_file->overwrite = true;
		$import_file->required = true;
		$import_file->attr('required', true);
		$form->add($import_file);

		/** @var InputfieldSubmit */
		$submit = $this->modules->get("InputfieldSubmit");
		$submit->name = 'submit';
		$submit->value = $this->_('Import');
		$form->add($submit);

		return $form;
	}

}
