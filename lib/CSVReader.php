<?php

namespace ImportTool;

class CSVReader extends Reader {

	protected $settings;
	protected $file_pointer;
	protected $filename;
	protected $header = [];

	public function __construct(array $settings = []) {
		$this->settings = $settings;
	}

	public function open(string $filename): bool {
		$this->file_pointer = fopen($filename, 'r');
		if ($this->file_pointer !== false) {
			$this->filename = $filename;
			$this->header = fgetcsv(
				$this->file_pointer,
				0,
				$this->settings['delimiter'] ?? ',',
				$this->settings['enclosure'] ?? '"'
			);
			return true;
		}
		return false;
	}

	public function read() {
		$line = fgetcsv(
			$this->file_pointer,
			0,
			$this->settings['delimiter'] ?? ',',
			$this->settings['enclosure'] ?? '"'
		);
		return $line !== false && !empty($this->header) ? array_combine($this->header, $line) : $line;
	}

	public function close() {
		fclose($this->file_pointer);
	}
}
