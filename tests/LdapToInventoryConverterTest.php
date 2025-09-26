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
 
        $result =  $converter->getSupportedItemtypes();

        $this->assertContains('Computer, Phone', $result);

    }
}