<?php namespace ProcessWire;

class ImportTool extends WireData implements Module {

	protected $profile;

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

	public function importFromFile(string $filename, array $args = []): array {

		$file_ext = pathinfo($filename, PATHINFO_EXTENSION);
		$reader = null;

		switch ($file_ext) {

			case 'csv':
				$reader = new \ImportTool\CSVReader($this->profile['reader_settings'] ?? []);
				break;

			case 'json':
				$reader = new \ImportTool\JSONReader($this->profile['reader_settings'] ?? []);
				break;

			case 'xml':
				$reader = new \ImportTool\XMLReader($this->profile['reader_settings'] ?? []);
				break;

			default:
				$this->error(sprintf(
					$this->_('Unsupported file extension "%s"'),
					$file_ext
				));
				return [];
		}

		$reader->open($filename);

		$count = [
			'imported' => 0,
			'updated' => 0,
			'skipped' => 0,
			'row_num' => 0,
			'time' => Debug::timer(),
		];

		while ($row = $reader->read()) {
			++$count['row_num'];
			if (empty($row) || count($row) == 1 && empty($row[0])) {
				continue;
			}
			$imported_page = $this->importPage($row);
			if ($imported_page) {
				++$count[$imported_page->_import_tool_action];
				if (!empty($this->profile['limit']) && $count['imported'] >= $this->profile['limit']) {
					break;
				}
				$this->pages->uncache($imported_page);
			}
		}

		$reader->close();
		if (!empty($args['unlink'])) $this->files->unlink($filename);
		$count['time'] = Debug::timer($count['time']);

		return $count;
	}

	/**
	 * @param array|\SimpleXMLElement $data
	 */
	protected function importPage($data) {

		if (empty($this->profile['template'])) {
			$this->error($this->_('Missing required template'));
			return false;
		}

		if (empty($this->profile['parent'])) {
			$this->error($this->_('Missing required parent page'));
			return false;
		}

		$page = $this->pages->newPage([
			'template' => $this->profile['template'],
			'parent' => $this->profile['parent'],
		]);
		$page->_import_tool_data = [];
		$page->setTrackChanges(true);

		foreach ($this->profile['values'] as $column_name => $column) {
			if (empty($column)) continue;
			$value = null;
			if (is_object($data)) {
				$value_options = (strpos($column_name, ':') !== false && $data instanceof \SimpleXMLElement)
					? $data->children(explode(':', $column_name)[0], true)
					: $data->{$column_name};
				$value_name = strpos($column_name, ':') !== false ? explode(':', $column_name)[1] : $column_name;
				if (!empty($value_options)) {
					$value = [];
					foreach ($value_options as $value_option_name => $value_option_value) {
						if ($value_option_name != $value_name) continue;
						$value[] = $value_option_value;
					}
				}
				$value = empty($value)
					? null
					: (
						count($value) === 1
							? $value[0]
							: $value
					);
			} else {
				$value = $data[$column_name] ?? null;
			}
			if ($value !== null && !empty($column['sanitize'])) {
				$value = is_object($value) || $column['sanitize'] === 'string' ? (string) $value : $value;
				if (!is_string($column['sanitize']) && is_callable($column['sanitize'])) {
					$value = $column['sanitize']($value, [
						'data' => $data,
					]);
				} else if ($column['sanitize'] !== 'string') {
					$value = $this->sanitizer->sanitize($value, $column['sanitize']);
				}
			}
			$callback = empty($column['callback']) ? null : $column['callback'];
			if (is_string($callback) && strpos($column['callback'], 'ImportTool.') === 0) {
				$callback = $this->getDotArray($column['callback']);
			}
			if ($this->isCallable($callback)) {
				$callback_result = $callback($page, $column['field'] ?? $column_name ?? '_' . $column_name, $value, [
					'data' => $data,
				]);
				if ($callback_result === 'after_save') {
					$import_data = $page->_import_tool_data;
					$import_data[$column['field'] ?? $column_name ?? '_import_tool_field_' . $column_name] = $callback;
					$page->_import_tool_data = $import_data;
				}
			} else {
				$this->importPageValue($page, $column['field'] ?? $column_name, $value);
			}
		}

		if (!$page->name) {
			$this->error($this->_('Unable to import page, no "name" or "title" field defined.'));
			if ($this->user->isSuperuser()) {
				$this->error("<pre>" . print_r($data, true) . "</pre>", Notice::allowMarkup);
			}
			return false;
		}

		$existing_page = null;

		if (!empty($this->profile['is_duplicate']) && is_string($this->profile['is_duplicate'])) {
			$existing_page_selector = $this->profile['is_duplicate'];
			if (strpos($existing_page_selector, '{') !== false) {
				preg_match_all('/{(.*?)}/', $existing_page_selector, $tags);
				$tags = empty($tags) ? [] : array_filter($tags[1]);
				foreach ($tags as $tag) {
					$existing_page_selector = str_replace(
						'{' . $tag . '}',
						wire()->sanitizer->selectorValue($page->get($tag)),
						$existing_page_selector
					);
				}
			}
			$existing_page = $this->pages->get($existing_page_selector);
		} else {
			$existing_page = $this->pages->get(implode(', ', [
				'parent_id' => 'parent_id=' . (int) $this->profile['parent'],
				'name' => 'name=' . $this->sanitizer->selectorValue($page->name, [
					'maxLength' => 128,
				]),
				'include=all',
			]));
		}

		if ($existing_page && $existing_page->id) {
			$on_duplicate = $this->profile['on_duplicate'] ?? 'continue';
			if ($on_duplicate === 'make_unique') {
				$page->name = $this->pages->names()->uniquePageName([
					'name' => $page->name,
					'parent' => $this->pages->get($this->profile['parent']),
				]);
				$page->save();
				$page->_import_tool_action = 'imported';
			} else if ($on_duplicate === 'update_existing') {
				$page = $this->updatePage($existing_page, $page, $data);
				if (!$page) {
					$this->pages->uncache($existing_page);
					return false;
				}
				$page->_import_tool_action = 'updated';
			} else if (!empty($this->profile['is_duplicate']) && is_string($this->profile['is_duplicate'])) {
				$this->message(sprintf(
					$this->_('Skipping duplicate page: %s'),
					$page->url
				));
				$page->_import_tool_action = 'skipped';
				$this->pages->uncache($existing_page);
				return $page;
			} else {
				$this->message(sprintf(
					$this->_('Skipping page with duplicate name: %s'),
					$page->url
				));
				$page->_import_tool_action = 'skipped';
				$this->pages->uncache($existing_page);
				return $page;
			}
		} else {
			$page->save();
		}

		if ($page->id && !empty($page->_import_tool_data)) {
			foreach ($page->_import_tool_data as $field => $data_value) {
				if ($this->isCallable($data_value)) {
					$data_column_name = strpos($field, '_import_tool_field_') === 0 ? substr($field, 20) : $field;
					$value = null;
					if (is_object($data)) {
						$value = strpos($data_column_name, ':') !== false && $data instanceof \SimpleXMLElement
							? ($data->children(explode(':', $data_column_name)[0], true)->{explode(':', $data_column_name)[1]} ?? null)
							: ($data->{$data_column_name} ?? null);
						if (!empty($value) && $value->children()) {
							$temp_value = [];
							foreach ($value as $value_item) {
								$temp_value[] = $value_item;
							}
							$value = $temp_value;
						} else {
							$value = $value;
						}
					} else {
						$value = $data[$column_name] ?? null;
					}
					$data_value($page, $field, $value, [
						'data' => $data,
					]);
				} else {
					$page->set($field, $data_value);
				}
			}
			$page->save();
		}

		$page->_import_tool_action = $page->_import_tool_action ?: 'imported';
		return $page;
	}

	protected function importPageValue(Page $page, $name, $value): bool {

		if ($name === 'name') {

			$page->name = $this->sanitizer->pageName($value, Sanitizer::translate);

		} else if ($name === 'status') {

			$page->status = $value;

		} else {

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
		$import_data = $page->_import_tool_data;

		foreach ($this->profile['values'] as $column_name => $column) {

			$value = null;
			if (isset($import_data[$column_name])) {
				if ($this->isCallable($import_data[$column_name])) {
					$import_data[$column_name](
						$existing_page,
						$column['field'] ?? $column_name ?? '_import_tool_field_' . $column_name,
						$data[$column_name] ?? '',
						[
							'data' => $data,
						]
					);
					continue;
				} else {
					$value = $import_data[$column_name];
				}
			} else if (isset($column['callback'])) {
				$callback = empty($column['callback']) ? null : $column['callback'];
				if (is_string($callback) && strpos($column['callback'], 'ImportTool.') === 0) {
					$callback = $this->getDotArray($column['callback']);
				}
				if ($this->isCallable($callback)) {
					$callback(
						$existing_page,
						$column['field'] ?? $column_name ?? '_import_tool_field_' . $column_name,
						$data[$column_name],
						[
							'data' => $data,
						]
					);
				}
				continue;
			} else {
				$value = $page->get($column['field'] ?? $column_name);
			}

			$name = $column['field'] ?? $column_name;

			$field = $this->wire()->fields->get($name);
			$existing_value = $field && $field->name ? $existing_page->get($field->name) : null;

			$existing_page->set($field && $field->name ? $field->name : $name, $value);

			if ($field && $field->type instanceof FieldtypePage) {
				if (((string) $existing_value) === ((string) $page->get($field->name))) {
					$existing_page->untrackChange($field->name);
				}
			}
		}

		$existing_page->save();
		return $existing_page;
	}

	protected function isCallable($function): bool {
		return $function !== null
			&& is_callable($function)
			&& (!is_object($function) || $function instanceof \Closure);
	}

	protected function getDotArray(string $key) {
		if (strpos($key, 'ImportTool.') === 0) {
			$key = substr($key, 11);
		}
		$parts = explode('.', $key);
		$current_item = $this->config->ImportTool ?: [];
		foreach ($parts as $index => $part) {
			$current_item = $current_item[$part] ?? null;
			if ($current_item === null) {
				return null;
			}
		}
		return $current_item;
	}

}
