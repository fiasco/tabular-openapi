# Tabular OpenApi

Store OpenApi schema objects in tabular data structures.

This library allows you to map OpenApi schemas into tables that could be further 
converted into CSV, SQL or data lake storage (e.g. Domo).

## Install
This library depends on cebe/php-openapi and can be installed with composer:

```
composer require fiasco/tabular-openapi
```

## Usage

Load the schema

```php
use Fiasco\TabularOpenapi\Schema;

$schema = new Schema('https://path/to/openapi-spec.json');
```

Get an OpenApi schema component to insert data into.


```php
$table = $schema->getTable('Report');
```

Insert your OpenApi compliant schema object into the table:

```php
$table->addRow($report);
```

Loop over the schema tables to discover data in the tables. Tabular OpenApi has a dev dependency on Symfony Console
so you can output tablular data to console during testing:

```php

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

$io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput());

foreach ($schema->getTables() as $name => $table) {
    $io->title($name.': '.$table->getRowsTotal() . ' ('.$table->uuid.')');
    $rows = [];
    foreach ($table->fetchAll() as $row) {
        $rows[] = $row;
    }
    $headers = array_keys(reset($rows) ?: []);

    $io->table($headers, $rows);
    $io->text('----');
}
```