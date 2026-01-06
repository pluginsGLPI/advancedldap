<?php

/**
 * -------------------------------------------------------------------------
 * AdvancedLDAP plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of AdvancedLDAP.
 *
 * AdvancedLDAP is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
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
 * @author    GLPI-Project
 * @copyright Copyright (C) GLPI-Project
 * @license   GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://github.com/pluginsGLPI/advancedldap
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Advancedldap\Inventory;

use Phone;

/**
 * Inventory builder for Phone itemtype.
 *
 * Maps GLPI Phone fields to the JSON inventory format
 * expected by Glpi\Inventory\Inventory.
 */
class PhoneBuilder extends AbstractInventoryBuilder
{
    /**
     * Mapping of GLPI field names to JSON inventory paths.
     *
     * Phone uses the same hardware/bios structure as Computer
     * (inherits from MainAsset without prepare() override).
     */
    private const FIELD_TO_JSON = [
        // Hardware section
        'name'             => 'hardware.name',
        'uuid'             => 'hardware.uuid',
        'contact'          => 'hardware.lastloggeduser',
        'comment'          => 'hardware.description',

        // Bios section
        'serial'           => 'bios.ssn',
        'otherserial'      => 'bios.assettag',
        'manufacturers_id' => 'bios.smanufacturer',
        'phonemodels_id'   => 'bios.smodel',
        'phonetypes_id'    => 'hardware.chassis_type',
    ];

    public function getItemtype(): string
    {
        return Phone::class;
    }

    protected function getContentSections(): array
    {
        return [
            'hardware' => [],
            'bios'     => [],
        ];
    }

    protected function getJsonPathForField(string $glpi_field): ?string
    {
        return self::FIELD_TO_JSON[$glpi_field] ?? null;
    }

    /**
     * Get the list of GLPI fields supported by this builder.
     *
     * Useful for UI to show available mapping targets.
     *
     * @return array<string, string> Field name => JSON path
     */
    public static function getSupportedFields(): array
    {
        return self::FIELD_TO_JSON;
    }
}
