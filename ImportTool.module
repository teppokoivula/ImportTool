<?php namespace ProcessWire;

use ImportTool\CSVReader;

class ImportTool extends WireData implements Module {

	public $profile;

	public function init() {
		/** @var WireClassLoader */
		$classLoader = $this->wire('classLoader');
		if (!$classLoader->hasNamespace('ImportTool')) {
			$classLoader->addNamespace('ImportTool', __DIR__ . '/lib/');
		}
	}

	public function setProfile(string $profile) {
		$config = $this->config->ImportTool;
		$this->profile = $config['profiles'][$profile] ?? null;
		if (empty($this->profile)) {
			$this->error($this->_('Import profile not found'));
		}
	}

	public function importFromFile(string $filename): array {

		$reader = new CSVReader($this->profile['reader_settings'] ?? []);
		$reader->open($filename);

		$count = [
			'imported' => 0,
			'updated' => 0,
			'row_num' => 0,
		];

		while ($row = $reader->getRow()) {
			++$count['row_num'];
			if ($count['row_num'] === 1) continue;
			if (empty($row) || count($row) == 1 && empty($row[0])) continue;
			$imported_page = $this->importPage($row);
			if ($imported_page) {
				++$count[$imported_page->_import_tool_existing_page ? 'updated' : 'imported'];
			}
		}

		$reader->close();

		$this->files->unlink($filename);

		return $count;
	}

	protected function importPage(array $data) {

		$page = $this->pages->newPage([
			'template' => $this->profile['template'],
			'parent' => $this->profile['parent'],
		]);
		$page->_import_tool_data = [];
		$page->setTrackChanges(true);

		foreach ($this->profile['fields'] as $column => $field) {
			if (empty($field)) continue;
			$value = $data[$column] ?? null;
			if ($value !== null && !empty($field['sanitize'])) {
				if (is_callable($field['sanitize'])) {
					$value = $field['sanitize']($value, [
						'data' => $data,
					]);
				} else {
					$value = $this->sanitizer->sanitize($value, $field['sanitize']);
				}
			}
			if (!empty($field['callback']) && is_callable($field['callback'])) {
				$callback_result = $field['callback']($page, $field['name'] ?? '_' . $column, $value, [
					'data' => $data,
				]);
				if ($callback_result === 'after_save') {
					$import_data = $page->_import_tool_data;
					$import_data[$field['name'] ?? '_' . $column] = $field['callback'];
					$page->_import_tool_data = $import_data;
				}
			} else {
				$this->importPageValue($page, $field['name'], $value);
			}
		}

		if (!$page->name) {
			$this->error($this->_('Unable to import page, no "name" or "title" field defined.'));
			if ($this->user->isSuperuser()) {
				$this->error("<pre>" . print_r($data, true) . "</pre>", Notice::allowMarkup);
			}
			return false;
		}

		$existing_page = $this->pages->get([
			'parent_id' => $this->profile['parent'],
			'name' => $page->name,
		]);
		if ($existing_page->id) {
			$on_duplicate = $this->profile['on_duplicate'] ?? 'continue';
			if ($on_duplicate === 'make_unique') {
				$page->name = $this->pages->names()->uniquePageName([
					'name' => $page->name,
					'parent' => $this->pages->get($this->profile['parent']),
				]);
				$page->save();
			} else if ($on_duplicate === 'update_existing') {
				$page = $this->updatePage($existing_page, $page, $data);
				if (!$page) {
					return false;
				}
				$page->_import_tool_existing_page = true;
			} else {
				$this->message(sprintf(
					$this->_('Skipping page with duplicate name "%s"'),
					$page->name
				));
				return false;
			}
		} else {
			$page->save();
		}

		if ($page->id && !empty($page->_import_tool_data)) {
			foreach ($page->_import_tool_data as $name => $value) {
				if (is_callable($value)) {
					$value($page, $name, $data[strpos($name, '_') === 0 ? substr($name, 1) : $name], [
						'data' => $data,
					]);
				} else {
					$page->set($name, $value);
				}
			}
			$page->save();
		}

		return $page;
	}

	protected function importPageValue(Page $page, $name, $value): bool {

		$field = $this->fields->get($name);
		if (!$field) return false;

		if ($field->type instanceof FieldtypeFile) {

			// split delimeted data to an array
			$value = preg_split('/[\r\n\t|]+/', trim($value));
			if ($field->get('maxFiles') == 1) {
				$value = array_shift($value);
			}
			$import_data = $page->_import_tool_data;
			$import_data[$name] = $value;
			$page->_import_tool_data = $import_data;

		} else if ($field->type instanceof FieldtypePage) {

			$on_missing_page_ref = $this->profile['on_missing_page_ref'] ?? null;
			if ($on_missing_page_ref === 'create') {
				$field->setQuietly('_sanitizeValueString', 'create');
				$page->set($name, $value);
				$field->offsetUnset('_sanitizeValueString');
			} else {
				$page->set($name, $value);
			}

		} else if ($name === 'title') {

			$page->set($name, $value);
			if (!$page->name) {
				$page->name = $this->sanitizer->pageName($value, Sanitizer::translate);
			}

		} else {

			$page->set($name, $value);

		}

		return true;
	}

	protected function updatePage(Page $existing_page, Page $page, array $data) {

		if ($existing_page->template->id !== $page->template->id) {
			$this->error(sprintf(
				$this->_('Unable to update "%s" because it uses a different template (%s) than new page (%s)'),
				$existing_page->name,
				$existing_page->template->label ?: $existing_page->template->name,
				$page->template->label ?: $page->template->name
			));
			return false;
		}

		/** @var array */
		$data = $page->_import_tool_data;

		foreach ($this->profile['fields'] as $column => $field) {
			$value = $data[$column] ?? $page->get($field['name']);
			if (is_callable($value)) {

				$value($existing_page, $field['name'] ?? '_' . $column, $data[$column], [
					'data' => $data,
				]);

			} else if ($value !== null && !empty($field['sanitize'])) {

				if (is_callable($field['sanitize'])) {
					$value = $field['sanitize']($value, [
						'data' => $data,
					]);
				} else {
					$value = $this->sanitizer->sanitize($value, $field['sanitize']);
				}

				$field = $this->wire('fields')->get($field['name']);
				$existing_value = $existing_page->get($field['name']);
				$existing_page->set($field['name'], $value);

				if ($field->type instanceof FieldtypePage) {
					if (((string) $existing_value) === ((string) $page->get($field['name']))) {
						$existing_page->untrackChange($field['name']);
					}
				}

			}
		}

		$existing_page->save();
		return $existing_page;
	}

}
