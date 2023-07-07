<?php

namespace ImportTool;

class JSONReader extends Reader {

	protected $settings;
	protected $json;
	protected $data;
	protected $filename;

	public function __construct(array $settings = []) {
		$this->settings = $settings;
	}

	public function open(string $filename): bool {
		$this->json = file_get_contents($filename);
		if ($this->json !== false) {
			$this->filename = $filename;
			$this->data = json_decode($this->json, true);
			return true;
		}
		return false;
	}

	public function read() {
		return array_shift($this->data);
	}

	public function close() {
		// nothing to do
	}
}
