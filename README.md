Import Tool for ProcessWire CMS/CMF
-----------------------------------

Import Tool is a general purpose helper module for importing content via predefined import profiles. The Import Tool module processes provided import file using configuration provided via a "profile", while the Process Import Tool module provides a simple user interface for running imports.

Currently Import Tool supports importing content from CSV files, but the goal is to have it support more formats via readers that can be bundled with the module or implemented separately and plugged in via the API provided by this module. *This feature is planned, but not yet implemented.*

Many parts of Import Tool have been derived from the Import Pages CSV module by Ryan Cramer. If you're looking for a more mature import module with extensive GUI options, please check Import Pages CSV out.

**WARNING**: this module is currently considered an early beta release. Test carefully before installing it on a production site! The API of the module is also still subject to change; accessing it via the GUI is safe, but building upon it is not recommended.

## Getting started

1) Install Import Tool. Process Import Tool is installed automatically, a new page is created under the Setup page in Admin, and a permission called "import-tool" is added.
2) Add the "import-tool" permission to user roles that should have access to the Import Tool Admin GUI.
3) Define import profile for your data. Currently this can only be done via $config->ImportTools array:

```php
$config->ImportTools = [
	'profiles' => [
		'members' => [
			'label' => 'Members',
			'template' => 'member',
			'parent' => 1282,
			'fields' => [
				[
					'name' => 'title',
					'sanitize' => 'text',
				],
				[
					'name' => 'first_name',
					'sanitize' => 'text',
				],
				[
					'name' => 'last_name',
					'sanitize' => 'text',
				],
				[
					'name' => 'date_of_birth',
					'sanitize' => 'date',
				],
				[
					'name' => 'email',
					'sanitize' => 'email',
				],
			],
			'on_duplicate' => 'make_unique',
			'on_missing_page_ref' => 'create',
			'reader_settings' => [
				'delimiter' => ',',
				'enclosure' => '"',
			]
		],
	],
];
```

The profile above can import data from a CSV file with structure like this:

```
title,first_name,last_name,date_of_birth,gender,email
Helfand-Isa,Isa,Helfand,1999-03-21,1601,Isa.Helfand@example.com
Raimondo-Sophia,Sophia,Raimondo,2009-11-04,1602,Sophia.Raimondo@example.com
Ader-Rosene,Rosene,Ader,2000-11-02,1602,Rosene.Ader@example.com
```

To skip over a column in the source data, include an empty array or `null` in the fields array:

```php
			'fields' => [
				// first two columns from import file will be skipped
				null,
				null,
				[
					'name' => 'title',
					'sanitize' => 'text',
				],
```

The "sanitize" property can be any existing Sanitizer method name, or multiple comma-separated method names (this value gets passed to [$sanitizer->sanitize()](https://processwire.com/api/ref/sanitizer/sanitize/)). If built-in Sanitizer methods are not quite enough, you can also provide a callback:

```php
				[
					'name' => 'start_date',
					'sanitize' => function($value, $args) {
						if (!empty($value) && is_string($value)) {
							// remove extraneous day abbreviation (e.g. "mon 1.1.2023") from date
							$value = preg_replace('/^[a-z]+ */i', '', $value);
						}
						return wire()->sanitizer->date($value);
					}
				],
```

Args is an array of additional arguments and `$args['data']` contains the full data array for current row.

If you need more control over how a field value gets stored and/or processed, you can provide a callback to the fields row. If provided, this overrides the built-in import page value method:

```php
				[
					'name' => 'start_time',
					'callback' => function($page, $field_name, $value, $args) {
						// time provided as a separate column, but we want to combine it with date
						$page->start_date = implode(' ', array_filter([
							date('j.n.Y', $page->getUnformatted('start_date')),
							$value,
						]));
					}
				],
```

Note: field name is technically optional in case a callback function is provided, since you can disregard it anyway in whatever code your callback contains. Args is the same as for the sanitize callback, i.e. an array of additional arguments with `$args['data']` containing the full data array for current row.

In some cases aforementioned callback cannot be executed right away (e.g. in case repeater items are involved), in which case you can delay the execution to after the page has been saved by returning string "after_save" from the method when Page doesn't yet have an ID:

```php
				[
					'callback' => function($page, $field_name, $value, $args) {
						if (!$page->id) return 'after_save';
						if (empty(trim($value))) return;
						$block = $page->getUnformatted('content_blocks')->getNew();
						$block->setMatrixType('text_content_block');
						$block->text_content = '<p>'
							. implode('</p><p>', array_filter(preg_split("/\r\n|\r|\n/", $value)))
							. '</p>';
						$page->save('content_blocks');
					},
				],
```

You can add notes for import profiles. These will be displayed in the Import Tool Admin GUI once you select said import profile:

```php
$config->ImportTools = [
	'profiles' => [
		'members' => [
			'label' => 'Members',
			'notes' => 'title,first_name,last_name,date_of_birth,email',
```

4) Navigate to the Import Tool page in the Admin, select a profile and file, and hit "Import".
