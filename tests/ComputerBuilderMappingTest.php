<?php

/**
 * -------------------------------------------------------------------------
 * advancedldap plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of advancedldap.
 *
 * AdvancedLDAP is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * AdvancedLDAP is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdvancedLDAP. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2018-2023 by Teclib'.
 * @license   GPLv3+ https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://services.glpi-network.com
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Advancedldap\Tests;

use Glpi\Tests\DbTestCase;
use GlpiPlugin\Advancedldap\ComputerBuilderMapping;
use PHPUnit\Framework\Attributes\DataProvider;

use function Safe\json_encode;

/**
 * @method void assertTrue($condition, string $message = '')
 * @method void assertFalse($condition, string $message = '')
 * @method void assertEquals($expected, $actual, string $message = '')
 * @method void assertNotFalse($condition, string $message = '')
 * @method void assertNotEmpty($actual, string $message = '')
 * @method void assertIsArray($actual, string $message = '')
 * @method void assertGreaterThan($expected, $actual, string $message = '')
 * @method void assertArrayHasKey($key, $array, string $message = '')
 * @method void assertIsInt($actual, string $message = '')
 */
final class ComputerBuilderMappingTest extends DbTestCase
{
    public function testCreateWithDefaultsPopulatesAllSections(): void
    {
        $mapping = new ComputerBuilderMapping();
        $id = $mapping->createWithDefaults();

        $this->assertIsInt($id);
        /** @var int $id */
        $this->assertGreaterThan(0, $id);

        $loaded = new ComputerBuilderMapping();
        $loaded->getFromDB($id);

        foreach (['main', 'hardware', 'bios', 'operatingsystem', 'networks', 'cpus', 'memories', 'drives', 'storages'] as $section) {
            $content = $loaded->getSection($section);
            $this->assertIsArray($content);
            $this->assertNotEmpty($content);
        }
    }

    public function testGetSectionReturnsExpectedKeys(): void
    {
        $mapping = new ComputerBuilderMapping();
        $id = $mapping->createWithDefaults();
        $this->assertIsInt($id);
        /** @var int $id */

        $loaded = new ComputerBuilderMapping();
        $loaded->getFromDB($id);

        $hardware = $loaded->getSection('hardware');
        $this->assertIsArray($hardware);
        $this->assertArrayHasKey('name', $hardware);

        $bios = $loaded->getSection('bios');
        $this->assertIsArray($bios);
        $this->assertArrayHasKey('ssn', $bios);

        $os = $loaded->getSection('operatingsystem');
        $this->assertIsArray($os);
        $this->assertArrayHasKey('name', $os);
    }

    public function testResetSectionRestoresDefaultTemplate(): void
    {
        $mapping = new ComputerBuilderMapping();
        $id = $mapping->createWithDefaults();
        $this->assertIsInt($id);
        /** @var int $id */

        $loaded = new ComputerBuilderMapping();
        $loaded->getFromDB($id);

        $loaded->update([
            'id'       => $id,
            'hardware' => json_encode(['name' => 'modified']),
        ]);

        $loaded->getFromDB($id);
        $this->assertEquals(['name' => 'modified'], $loaded->getSection('hardware'));

        $loaded->resetSection('hardware');

        $loaded->getFromDB($id);

        $default = ComputerBuilderMapping::loadDefaultTemplate('hardware');
        $this->assertEquals($default, $loaded->getSection('hardware'));
    }

    public function testLoadDefaultTemplateAcceptsEveryDeclaredSection(): void
    {
        foreach (ComputerBuilderMapping::getSectionNames() as $section) {
            $content = ComputerBuilderMapping::loadDefaultTemplate($section);
            $this->assertIsArray($content);
            $this->assertNotEmpty($content, sprintf('Section "%s" should load its template', $section));
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function traversalSectionProvider(): iterable
    {
        yield 'plugin root traversal'   => ['../../../composer'];
        yield 'glpi root traversal'     => ['../../../../../composer'];
        yield 'sibling plugin traversal' => ['../../../../tag/composer'];
        yield 'current directory'       => ['.'];
        yield 'parent directory'        => ['..'];
        yield 'absolute path'           => ['/var/www/glpi/composer'];
        yield 'unknown section'         => ['not_a_section'];
        yield 'section with suffix'     => ['hardware/../../../composer'];
    }

    #[DataProvider('traversalSectionProvider')]
    public function testLoadDefaultTemplateRejectsPathTraversal(string $section): void
    {
        $this->assertEquals(
            [],
            ComputerBuilderMapping::loadDefaultTemplate($section),
            sprintf('Section "%s" must not resolve to a file outside the template directory', $section),
        );
    }
}
