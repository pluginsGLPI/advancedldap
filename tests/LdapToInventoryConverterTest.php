<?php

namespace GlpiPlugin\Advancedldap\Tests;

//use GlpiPlugin\Advancedldap\LdapToInventoryConverter;
use GlpiPlugin\Advancedldap\Services\LdapToInventoryConverter;

use DbTestCase;

class LdapToInventoryConverterTest extends DbTestCase
{
    public function testHighestFromStringsArray()
    {
        $converter = new LdapToInventoryConverter;
        
        $CFG_GLPI['inventory_types'] = [
    Computer::class,
    Phone::class,
    Printer::class,
    NetworkEquipment::class,
];
       
        $result =  $converter->getSupportedItemtypes();

        $this->assertContains('Computer, Phone', $result);

    }
}