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
use Fiasco\TabularOpenapi\TableManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Fiasco\TabularOpenapi\TableManager
 */
class TableManagerTest extends TestCase {

    public function testData() {
        $owner = [
            'name' => 'John Doe',
            'email' => 'john.doe@null.com'
        ];

        $line_item = [
            'sku' => 'PR-19273718',
            'quantity' => 1,
            'promotion' => true,
            'wishlist' => true,
            'campaign' => ['facebook', 'google']
        ];

        $cart = [
            'id' => 1,
            'items' => [ $line_item ],
            'itemsBySku' => [
                $line_item['sku'] => $line_item
            ],
            'timestamp' => new DateTime(),
            'owner' => $owner
        ];

        $table_manager = new TableManager(realpath(__DIR__.'/../tests.openapi.json'));
        $tableCart = $table_manager->getTable('cart');

        $this->assertInstanceOf(Table::class, $tableCart);
        $this->assertArrayHasKey('id', $tableCart->columns);
        $this->assertArrayHasKey('items', $tableCart->columns);
        $this->assertArrayHasKey('itemsBySku', $tableCart->columns);
        $this->assertArrayHasKey('timestamp', $tableCart->columns);
        $this->assertArrayHasKey('owner', $tableCart->columns);

        $tableCart->insertRow($cart);

        $lookupTable = $table_manager->buildLookupTable();
        $total = 0;
        foreach ($lookupTable->fetchAll() as $row) {
            $total++;
        }
        $this->assertEquals(3, $total);
    }
}