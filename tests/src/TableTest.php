<?php

namespace FiascoTests\TabularOpenapi;

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use DateTime;
use Fiasco\TabularOpenapi\Columns\CollapsedColumn;
use Fiasco\TabularOpenapi\Columns\Column;
use Fiasco\TabularOpenapi\Columns\DynamicColumns;
use Fiasco\TabularOpenapi\Columns\ObjectColumn;
use Fiasco\TabularOpenapi\Table;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Fiasco\TabularOpenapi\Table
 */
class TableTest extends TestCase {

    public function testData() {
        $openapi = Reader::readFromJsonFile(realpath(__DIR__.'/../tests.openapi.json'), OpenApi::class, false);
        $owner = [
            'name' => 'John Doe',
            'email' => 'john.doe@null.com'
        ];

        $tableOwner = new Table('owner', 
            new Column(name: 'name', schema: $openapi->components->schemas['owner']->properties['name'], tableName: 'owner'),
            new Column(name: 'email', schema: $openapi->components->schemas['owner']->properties['email'], tableName: 'owner')
        );

        $tableOwner->insertRow($owner);

        $row = $tableOwner->fetch(0)->current();

        $this->assertIsArray($row);
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayHasKey('email', $row);

        $line_item = [
            'sku' => 'PR-19273718',
            'quantity' => 1,
            'promotion' => true,
            'wishlist' => true,
            'campaign' => ['facebook', 'google']
        ];

        $tableLineItem = new Table('lineItem',
            new Column(name: 'sku', schema: $openapi->components->schemas['lineItem']->properties['sku'], tableName: 'lineItem'),
            new Column(name: 'quantity', schema: $openapi->components->schemas['lineItem']->properties['quantity'], tableName: 'lineItem'),
            new DynamicColumns(additionalProperties: $openapi->components->schemas['lineItem']->additionalProperties, tableName: 'lineItem')
        );

        $tableLineItem->insertRow($line_item);
        $generator = $tableLineItem->fetch(0);

        $row = $generator->current();
        $this->assertIsArray($row);
        $this->assertArrayHasKey('sku', $row);
        $this->assertArrayHasKey('quantity', $row);
        $this->assertArrayHasKey('promotion', $row);
        $this->assertArrayHasKey('wishlist', $row);
        $this->assertArrayHasKey('campaign', $row);
        $this->assertIsString($row['campaign']);
        //$this->assertEquals('facebook', $row['campaign']);

        $generator->next();
        $row = $generator->current();
        $this->assertIsArray($row);
        $this->assertArrayHasKey('sku', $row);
        $this->assertArrayHasKey('quantity', $row);
        $this->assertArrayHasKey('promotion', $row);
        $this->assertArrayHasKey('wishlist', $row);
        $this->assertArrayHasKey('campaign', $row);
        $this->assertIsString($row['campaign']);
        $this->assertEquals('google', $row['campaign']);

        $total = 0;
        foreach ($tableLineItem->fetchAll() as $row) {
            $total++;
        }
        $this->assertEquals(2, $total);

        $line_item2 = [
            'sku' => 'PR-9384028',
            'quantity' => 2,
            'wishlist' => true,
            'campaign' => ['instagram']
        ];

        $cart = [
            'id' => 1,
            'items' => [ $line_item ],
            "itemsBySku" => [
                $line_item['sku'] => $line_item,
                $line_item2['sku'] => $line_item2
            ],
            'timestamp' => new DateTime(),
            'owner' => $owner
        ];

        $tableCart = new Table('cart', 
            new Column(name: 'id', schema: $openapi->components->schemas['cart']->properties['id'], tableName: 'cart'),
            new CollapsedColumn(name: 'items', schema: $openapi->components->schemas['cart']->properties['items'], tableName: 'cart'),
            new ObjectColumn(name: "itemsBySku", schema: $openapi->components->schemas['cart']->properties['itemsBySku'], tableName: 'cart'),
            new Column(name: 'timestamp', schema: $openapi->components->schemas['cart']->properties['timestamp'], tableName: 'cart'),
            new ObjectColumn(name: 'owner', schema: $openapi->components->schemas['cart']->properties['owner'], tableName: 'cart')
        );
    

        $tableCart->insertRow($cart);
        $generator = $tableCart->fetch(0);
        $row = $generator->current();

        $this->assertIsArray($row);
        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey('items', $row);
        $this->assertIsNotArray($row['items']);
        $this->assertArrayHasKey('itemsBySku', $row);
        $this->assertEquals('PR-19273718', $row['itemsBySku']);
        $this->assertArrayHasKey('timestamp', $row);
        $this->assertArrayHasKey('owner', $row);
        $this->assertIsString($row['owner']);
        $this->assertIsArray($tableCart->getReference('#/components/schemas/owner', 'owner', $row['owner']));

        $generator->next();
        $row = $generator->current();

        $this->assertIsArray($row);
        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey('items', $row);
        $this->assertIsNotArray($row['items']);
        $this->assertArrayHasKey('itemsBySku', $row);
        $this->assertEquals('PR-9384028', $row['itemsBySku']);
        $this->assertArrayHasKey('timestamp', $row);
        $this->assertArrayHasKey('owner', $row);
        $this->assertIsString($row['owner']);
        $this->assertIsArray($tableCart->getReference('#/components/schemas/owner', 'owner', $row['owner']));

        $total = 0;
        foreach ($tableCart->fetchAll() as $row) {
            $total++;
        }
        $this->assertEquals(2, $total);
    }
}