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
		if (!$config) {
			$this->error($this->_('Module configuration is missing'));
			return false;
		}

		$import_profile_name = $form->getChildByName('import_profile')->value;
		$import_profile = $config['profiles'][$import_profile_name] ?? null;
		if (empty($import_profile)) {
			$this->error($this->_('Missing required import profile'));
			return false;
		}

		$this->session->setFor('ImportTool', 'import_profile_name', $import_profile_name);

		if ($config['allow_overriding_profile_configuration']) {
			$profile_configuration_field = $form->getChildByName('profile_configuration');
			$profile_configuration = $profile_configuration_field ? $profile_configuration_field->value : null;
			if ($profile_configuration) {
				$this->session->setFor('ImportTool', 'profile_configuration', $profile_configuration);
				$profile_configuration_array = json_decode($profile_configuration, true);
				if (!is_array($profile_configuration_array)) {
					$this->error($this->_('Invalid import configuration'));
					return false;
				}
				$config['profiles'][$import_profile_name] = array_merge($config['profiles'][$import_profile_name], $profile_configuration_array);
				$this->config->ImportTool = $config;
			}
		}

		$import_file_ext = pathinfo($import_file, PATHINFO_EXTENSION);
		$import_file_name = 'import-' . time() . '-' . $this->user->id . '.' . $import_file_ext;
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
					sprintf($this->_('%d rows processed in %s seconds'), $count['row_num'], $count['time']),
					sprintf($this->_('peak memory usage %s'), wireBytesStr(memory_get_peak_usage())),
					empty($count['imported']) ? null : sprintf($this->_('%d pages imported'), $count['imported']),
					empty($count['updated']) ? null : sprintf($this->_('%d pages updated'), $count['updated']),
					empty($count['skipped']) ? null : sprintf($this->_('%d pages skipped'), $count['skipped']),
				])));
				$this->session->redirect($this->page->url, false);
			}
		}
	}

	protected function getForm(): InputfieldForm {


		/** @var InputfieldForm $form */
		$form = $this->modules->get('InputfieldForm');
		$form->id = 'process-import-tool-form';

		$config = $this->config->ImportTool;
		if (!$config) {
			$this->error(
				$this->_('Import profiles must be defined via $config->ImportTool before you can import content.'),
			);
		}

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
		if ($config && count($config['profiles'])) {
			foreach ($config['profiles'] as $profile_name => $profile_data) {
				$import_profile->addOption($profile_name, $profile_data['label'] ?? $profile_name, [
					'data-notes' => $profile_data['notes'] ?? '',
				]);
			}
		}
		$form->add($import_profile);

		$import_profile_name = $this->session->getfor('ImportTool', 'import_profile_name');
		if ($import_profile_name) {
			$import_profile->appendMarkup(<<<HTML
<script>
var importProfile = document.getElementById('Inputfield_import_profile')
importProfile.value = '$import_profile_name'
importProfile.dispatchEvent(new Event('change'))
</script>
HTML);
		}

		/** @var InputfieldFile */
		$import_file = $this->modules->get('InputfieldFile');
		$import_file->name = 'import_file';
		$import_file->label = $this->_('Import file');
		$import_file->icon = 'file-text';
		$import_file->extensions = 'csv txt json xml';
		$import_file->maxFiles = 1;
		$import_file->descriptionRows = 0;
		$import_file->overwrite = true;
		$import_file->required = true;
		$import_file->attr('required', true);
		$form->add($import_file);

		if ($config && !empty($config['allow_overriding_profile_configuration'])) {
			/** @var InputfieldTextarea */
			$profile_configuration = $this->modules->get('InputfieldTextarea');
			$profile_configuration->name = 'profile_configuration';
			$profile_configuration->value = $this->session->getFor('ImportTool', 'profile_configuration');
			$profile_configuration->label = $this->_('Import profile configuration');
			$profile_configuration->icon = 'code';
			$profile_configuration->description = $this->_('You can override profile configuration settings here. Provided settings are merged with preconfigured profile settings runtime. Please note that this feature is considered advanced and should only be used if you know what you are doing.');
			$profile_configuration->notes = sprintf(
				$this->_('Provide configuration settings as JSON. Example: %s'),
				'`{"parent": 1234, "limit": 100}`',
			);
			$profile_configuration->rows = 10;
			$form->add($profile_configuration);
		}

		/** @var InputfieldSubmit */
		$submit = $this->modules->get("InputfieldSubmit");
		$submit->name = 'submit';
		$submit->value = $this->_('Import');
		$submit->id = 'process-import-tool-submit';
		if (!$config) {
			$submit->attr('disabled', true);
			$submit->attr('style', 'pointer-events: none');
			$submit->addClass('ui-state-disabled');
		}
		$form->add($submit);

		return $form;
	}

}
