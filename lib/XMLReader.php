<?php

namespace ImportTool;

class XMLReader extends Reader {

	protected $settings;
	protected $filename;
	protected $xml;
	protected $index = 0;

	public function __construct(array $settings = []) {
		$this->settings = $settings;
	}

	public function open(string $filename): bool {
		$data = file_get_contents($filename);
		$this->xml = simplexml_load_string($data);
		return $this->xml === false ? false : true;
	}

	public function read() {
		$node = $this->xml->channel->item[$this->index] ?? null;
		if ($node) {
			++$this->index;
			return $node;
		}
		return null;
	}

	public function close() {
		// nothing to do
	}
}
