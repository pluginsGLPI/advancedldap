<?php

namespace GlpiPlugin\Advancedldap\Tests;

use GlpiPlugin\Advancedldap\Services\SyncFilterValidationService;
use GlpiPlugin\Advancedldap\Contracts\LdapFilterSanitizerInterface;
use GlpiPlugin\Advancedldap\Contracts\LdapFilterParserInterface;
use GlpiPlugin\Advancedldap\Contracts\LdapAttributeMapperInterface;
use GlpiPlugin\Advancedldap\Contracts\AutoMappingServiceInterface;
use DbTestCase;

/**
 * Unit tests for SyncFilterValidationService
 * Tests validation and preparation of SyncFilter inputs
 */
class SyncFilterValidationServiceTest extends DbTestCase
{
    private $validationService;
    private $sanitizer;
    private $filterParser;
    private $attributeMapper;
    private $autoMappingService;

    public function setUp(): void
    {
        parent::setUp();

        // Create mocks for dependencies
        $this->sanitizer = $this->createMock(LdapFilterSanitizerInterface::class);
        $this->filterParser = $this->createMock(LdapFilterParserInterface::class);
        $this->attributeMapper = $this->createMock(LdapAttributeMapperInterface::class);
        $this->autoMappingService = $this->createMock(AutoMappingServiceInterface::class);

        // Create service instance with mocked dependencies
        $this->validationService = new SyncFilterValidationService(
            $this->sanitizer,
            $this->filterParser,
            $this->attributeMapper,
            $this->autoMappingService
        );
    }

    /**
     * Test constructor properly initializes the service
     */
    public function testConstructor()
    {
        $this->assertInstanceOf(SyncFilterValidationService::class, $this->validationService);
    }

    /**
     * Test constructor without AutoMappingService (optional dependency)
     */
    public function testConstructorWithoutAutoMapping()
    {
        $service = new SyncFilterValidationService(
            $this->sanitizer,
            $this->filterParser,
            $this->attributeMapper,
            null
        );

        $this->assertInstanceOf(SyncFilterValidationService::class, $service);
    }

    /**
     * Test validateLdapInputs with valid Base DN
     */
    public function testValidateLdapInputsWithValidBaseDn()
    {
        // Arrange
        $input = [
            'base_dn' => 'ou=users,dc=example,dc=com',
        ];

        $this->sanitizer
            ->expects($this->once())
            ->method('isValidDN')
            ->with('ou=users,dc=example,dc=com')
            ->willReturn(true);

        // Act
        $result = $this->validationService->validateLdapInputs($input);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('ou=users,dc=example,dc=com', $result['base_dn']);
    }

    /**
     * Test validateLdapInputs with invalid Base DN
     * Should return false and add error message
     */
    public function testValidateLdapInputsWithInvalidBaseDn()
    {
        // Arrange
        $input = [
            'base_dn' => 'invalid-dn-format',
        ];

        $this->sanitizer
            ->expects($this->once())
            ->method('isValidDN')
            ->with('invalid-dn-format')
            ->willReturn(false);

        // Act
        $result = $this->validationService->validateLdapInputs($input);

        // Assert
        $this->assertFalse($result, 'Should return false for invalid Base DN');

        // Verify error message was added
        $this->hasSessionMessages(ERROR, ['Invalid LDAP Base DN syntax. Please check the format (example: ou=users,dc=example,dc=com)']);
    }

    /**
     * Test validateLdapInputs with valid LDAP filter
     */
    public function testValidateLdapInputsWithValidFilter()
    {
        // Arrange
        $input = [
            'ldap_filter' => '(objectClass=person)',
        ];

        $sanitizedFilter = '(objectClass=person)';

        $this->sanitizer
            ->expects($this->once())
            ->method('sanitizeFilter')
            ->with('(objectClass=person)')
            ->willReturn($sanitizedFilter);

        // Act
        $result = $this->validationService->validateLdapInputs($input);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($sanitizedFilter, $result['ldap_filter']);
    }

    /**
     * Test validateLdapInputs with invalid LDAP filter
     * Should return false and add error message
     */
    public function testValidateLdapInputsWithInvalidFilter()
    {
        // Arrange
        $input = [
            'ldap_filter' => '(objectClass=person',  // Missing closing parenthesis
        ];

        $this->sanitizer
            ->expects($this->once())
            ->method('sanitizeFilter')
            ->with('(objectClass=person')
            ->willReturn(null);

        // Act
        $result = $this->validationService->validateLdapInputs($input);

        // Assert
        $this->assertFalse($result, 'Should return false for invalid LDAP filter');

        // Verify error message was added
        $this->hasSessionMessages(ERROR, ['Invalid LDAP filter syntax. Please check your filter format (example: (objectClass=person))']);
    }

    /**
     * Test validateLdapInputs with empty values
     * Should pass validation (empty values are allowed)
     */
    public function testValidateLdapInputsWithEmptyValues()
    {
        // Arrange
        $input = [
            'base_dn' => '',
            'ldap_filter' => '',
        ];

        // Sanitizer methods should not be called for empty values
        $this->sanitizer
            ->expects($this->never())
            ->method('isValidDN');

        $this->sanitizer
            ->expects($this->never())
            ->method('sanitizeFilter');

        // Act
        $result = $this->validationService->validateLdapInputs($input);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($input, $result);
    }

    /**
     * Test validateLdapInputs with both valid Base DN and filter
     */
    public function testValidateLdapInputsWithBothValues()
    {
        // Arrange
        $input = [
            'base_dn' => 'ou=users,dc=test,dc=com',
            'ldap_filter' => '(cn=*)',
        ];

        $this->sanitizer
            ->expects($this->once())
            ->method('isValidDN')
            ->with('ou=users,dc=test,dc=com')
            ->willReturn(true);

        $this->sanitizer
            ->expects($this->once())
            ->method('sanitizeFilter')
            ->with('(cn=*)')
            ->willReturn('(cn=*)');

        // Act
        $result = $this->validationService->validateLdapInputs($input);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('ou=users,dc=test,dc=com', $result['base_dn']);
        $this->assertEquals('(cn=*)', $result['ldap_filter']);
    }

    /**
     * Test prepareMappingsInput with asset_fields array
     */
    public function testPrepareMappingsInputWithAssetFields()
    {
        // Arrange
        $input = [
            'asset_fields' => ['name', 'serial'],
            'ldap_filter' => '(objectClass=computer)',
        ];

        $this->filterParser
            ->expects($this->once())
            ->method('parseFilterAttributes')
            ->with('(objectClass=computer)')
            ->willReturn(['cn', 'serialNumber', 'objectClass']);

        $this->attributeMapper
            ->expects($this->exactly(2))
            ->method('findMatchingAttribute')
            ->willReturnOnConsecutiveCalls('cn', 'serialNumber');

        // Act
        $result = $this->validationService->prepareMappingsInput($input);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('field_mappings', $result);
        $mappings = json_decode($result['field_mappings'], true);
        $this->assertEquals('cn', $mappings['name']);
        $this->assertEquals('serialNumber', $mappings['serial']);
    }

    /**
     * Test prepareMappingsInput with field_mappings array
     * Should convert array to JSON
     */
    public function testPrepareMappingsInputWithFieldMappingsArray()
    {
        // Arrange
        $input = [
            'field_mappings' => ['name' => 'cn', 'serial' => 'serialNumber'],
        ];

        // Act
        $result = $this->validationService->prepareMappingsInput($input);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('field_mappings', $result);
        $this->assertIsString($result['field_mappings']);
        $mappings = json_decode($result['field_mappings'], true);
        $this->assertEquals('cn', $mappings['name']);
        $this->assertEquals('serialNumber', $mappings['serial']);
    }

    /**
     * Test prepareMappingsInput with automatic mapping
     * Should apply default mappings for inventoriable asset types
     */
    public function testPrepareMappingsInputWithAutoMapping()
    {
        // Arrange
        $input = [
            'asset_type' => 'Computer',
        ];

        $defaultMappings = [
            'name' => 'cn',
            'serial' => 'serialNumber',
        ];

        $this->autoMappingService
            ->expects($this->once())
            ->method('getDefaultMappings')
            ->with('Computer')
            ->willReturn($defaultMappings);

        // Act
        $result = $this->validationService->prepareMappingsInput($input);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('field_mappings', $result);
        $mappings = json_decode($result['field_mappings'], true);
        $this->assertEquals($defaultMappings, $mappings);

        // The service logs debug information about auto-mapping, which is expected
        $this->hasPhpLogRecordThatContains('AdvancedLDAP: Applied automatic field mappings for Computer', 'DEBUG');
    }

    /**
     * Test prepareMappingsInput without AutoMappingService
     * Should not crash when auto-mapping is unavailable
     */
    public function testPrepareMappingsInputWithoutAutoMappingService()
    {
        // Arrange
        $service = new SyncFilterValidationService(
            $this->sanitizer,
            $this->filterParser,
            $this->attributeMapper,
            null // No auto-mapping service
        );

        $input = [
            'asset_type' => 'Computer',
        ];

        // Act
        $result = $service->prepareMappingsInput($input);

        // Assert
        $this->assertIsArray($result);
        // Should not have auto-generated mappings
        $this->assertArrayNotHasKey('field_mappings', $result);
    }

    /**
     * Test prepareMappingsInput with legacy asset_field (single field)
     */
    public function testPrepareMappingsInputWithLegacyAssetField()
    {
        // Arrange
        $input = [
            'asset_field' => 'name',
            'ldap_filter' => '(objectClass=computer)',
        ];

        $this->filterParser
            ->expects($this->once())
            ->method('parseFilterAttributes')
            ->with('(objectClass=computer)')
            ->willReturn(['cn', 'serialNumber']);

        $this->attributeMapper
            ->expects($this->once())
            ->method('findMatchingAttribute')
            ->with('name', ['cn', 'serialNumber'])
            ->willReturn('cn');

        // Act
        $result = $this->validationService->prepareMappingsInput($input);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('field_mappings', $result);
        $mappings = json_decode($result['field_mappings'], true);
        $this->assertEquals('cn', $mappings['name']);
    }

    /**
     * Test prepareMappingsInput with empty asset_fields
     * Should not create mappings
     */
    public function testPrepareMappingsInputWithEmptyAssetFields()
    {
        // Arrange
        $input = [
            'asset_fields' => [],
        ];

        // Parser should not be called for empty fields
        $this->filterParser
            ->expects($this->never())
            ->method('parseFilterAttributes');

        // Act
        $result = $this->validationService->prepareMappingsInput($input);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('field_mappings', $result);
    }

    /**
     * Test prepareMappingsInput filters out empty field values
     */
    public function testPrepareMappingsInputFiltersEmptyFields()
    {
        // Arrange
        $input = [
            'asset_fields' => ['name', '', 'serial', null],
            'ldap_filter' => '(objectClass=computer)',
        ];

        $this->filterParser
            ->expects($this->once())
            ->method('parseFilterAttributes')
            ->willReturn(['cn', 'serialNumber']);

        // Should only be called twice (for 'name' and 'serial')
        $this->attributeMapper
            ->expects($this->exactly(2))
            ->method('findMatchingAttribute')
            ->willReturnOnConsecutiveCalls('cn', 'serialNumber');

        // Act
        $result = $this->validationService->prepareMappingsInput($input);

        // Assert
        $this->assertIsArray($result);
        $mappings = json_decode($result['field_mappings'], true);
        $this->assertCount(2, $mappings);
    }

    /**
     * Test validateAndPrepare combines both validations
     */
    public function testValidateAndPrepare()
    {
        // Arrange
        $input = [
            'base_dn' => 'ou=users,dc=test,dc=com',
            'ldap_filter' => '(cn=*)',
            'asset_fields' => ['name'],
        ];

        $this->sanitizer
            ->expects($this->once())
            ->method('isValidDN')
            ->willReturn(true);

        $this->sanitizer
            ->expects($this->once())
            ->method('sanitizeFilter')
            ->willReturn('(cn=*)');

        $this->filterParser
            ->expects($this->once())
            ->method('parseFilterAttributes')
            ->willReturn(['cn']);

        $this->attributeMapper
            ->expects($this->once())
            ->method('findMatchingAttribute')
            ->willReturn('cn');

        // Act
        $result = $this->validationService->validateAndPrepare($input);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('ou=users,dc=test,dc=com', $result['base_dn']);
        $this->assertEquals('(cn=*)', $result['ldap_filter']);
        $this->assertArrayHasKey('field_mappings', $result);
    }

    /**
     * Test validateAndPrepare returns false on validation error
     */
    public function testValidateAndPrepareReturnsFalseOnError()
    {
        // Arrange
        $input = [
            'base_dn' => 'invalid-dn',
        ];

        $this->sanitizer
            ->expects($this->once())
            ->method('isValidDN')
            ->willReturn(false);

        // Act
        $result = $this->validationService->validateAndPrepare($input);

        // Assert
        $this->assertFalse($result, 'Should return false when LDAP validation fails');

        // Verify error message was added
        $this->hasSessionMessages(ERROR, ['Invalid LDAP Base DN syntax. Please check the format (example: ou=users,dc=example,dc=com)']);
    }

    /**
     * Test prepareMappingsInput handles auto-mapping exception gracefully
     */
    public function testPrepareMappingsInputHandlesAutoMappingException()
    {
        // Arrange
        $input = [
            'asset_type' => 'Computer',
        ];

        $this->autoMappingService
            ->expects($this->once())
            ->method('getDefaultMappings')
            ->willThrowException(new \Exception('Auto-mapping error'));

        // Act
        $result = $this->validationService->prepareMappingsInput($input);

        // Assert - should continue without crashing
        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('field_mappings', $result);

        // The service logs debug information when auto-mapping fails, which is expected
        $this->hasPhpLogRecordThatContains('AdvancedLDAP: Auto-mapping failed for Computer', 'DEBUG');
    }

    /**
     * Test prepareMappingsInput does not auto-map when mappings exist
     */
    public function testPrepareMappingsInputSkipsAutoMappingWhenMappingsExist()
    {
        // Arrange
        $input = [
            'asset_type' => 'Computer',
            'field_mappings' => ['name' => 'cn'],
        ];

        // Auto-mapping should not be called
        $this->autoMappingService
            ->expects($this->never())
            ->method('getDefaultMappings');

        // Act
        $result = $this->validationService->prepareMappingsInput($input);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('field_mappings', $result);
        $mappings = json_decode($result['field_mappings'], true);
        $this->assertEquals('cn', $mappings['name']);
    }
}
