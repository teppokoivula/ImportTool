<?php

namespace ImportTool;

class CSVReader extends Reader {

	protected $filename;
	protected $file_pointer;
	protected $settings;

	public function __construct(array $settings = []) {
		$this->settings = $settings;
		ini_set('auto_detect_line_endings', $settings['auto_detect_line_endings'] ?? true);
	}

	public function open(string $filename): bool {
		$this->filename = $filename;
		$this->file_pointer = fopen($filename, 'r');
		return $this->file_pointer !== false;
	}

	public function getRow() {
		return fgetcsv(
			$this->file_pointer,
			0,
			$this->settings['delimiter'] ?? ',',
			$this->settings['enclosure'] ?? '"'
		);
	}

	public function close() {
		fclose($this->file_pointer);
	}
}
