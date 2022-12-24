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

```
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

4) Navigate to the Import Tool page in the Admin, select a profile and file, and hit "Submit".
