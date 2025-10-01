<?php

namespace GlpiPlugin\Advancedldap\Tests;

use DbTestCase;
use GlpiPlugin\Advancedldap\Services\LdapFilterParser;

class LdapFilterParserTest extends DbTestCase
{
    private $parser;

    public function setUp(): void
    {
        parent::setUp();

        // Create parser instance
        $this->parser = new LdapFilterParser();
    }

    /**
     * Test parseFilterAttributes with simple filter
     */
    public function testParseFilterAttributesWithSimpleFilter()
    {
        // Arrange
        $filter = '(cn=Computer01)';

        // Act
        $result = $this->parser->parseFilterAttributes($filter);

        // Assert
        $this->assertEquals(['cn'], $result);
    }

    /**
     * Test parseFilterAttributes with multiple attributes
     */
    public function testParseFilterAttributesWithMultipleAttributes()
    {
        // Arrange
        $filter = '(&(objectClass=computer)(cn=*)(description=Test))';

        // Act
        $result = $this->parser->parseFilterAttributes($filter);

        // Assert
        $this->assertEquals(['cn', 'description', 'objectClass'], $result);
    }

    /**
     * Test parseFilterAttributes with OR operator
     */
    public function testParseFilterAttributesWithOrOperator()
    {
        // Arrange
        $filter = '(|(sAMAccountName=test)(uid=test))';

        // Act
        $result = $this->parser->parseFilterAttributes($filter);

        // Assert
        $this->assertEquals(['sAMAccountName', 'uid'], $result);
    }

    /**
     * Test parseFilterAttributes with comparison operators
     */
    public function testParseFilterAttributesWithComparisonOperators()
    {
        // Arrange
        $filter = '(&(version>=10)(priority<=5)(type~=server))';

        // Act
        $result = $this->parser->parseFilterAttributes($filter);

        // Assert
        $this->assertEquals(['priority', 'type', 'version'], $result);
    }

    /**
     * Test parseFilterAttributes with wildcards
     */
    public function testParseFilterAttributesWithWildcards()
    {
        // Arrange
        $filter = '(cn=*Computer*)';

        // Act
        $result = $this->parser->parseFilterAttributes($filter);

        // Assert
        $this->assertEquals(['cn'], $result);
    }

    /**
     * Test parseFilterAttributes with duplicate attributes returns unique list
     */
    public function testParseFilterAttributesWithDuplicateAttributes()
    {
        // Arrange
        $filter = '(&(cn=Test)(cn=*)(cn=Computer))';

        // Act
        $result = $this->parser->parseFilterAttributes($filter);

        // Assert
        $this->assertEquals(['cn'], $result);
        $this->assertCount(1, $result);
    }

    /**
     * Test parseFilterAttributes with NOT operator
     */
    public function testParseFilterAttributesWithNotOperator()
    {
        // Arrange
        $filter = '(&(objectClass=computer)(!(disabled=true)))';

        // Act
        $result = $this->parser->parseFilterAttributes($filter);

        // Assert
        $this->assertEquals(['disabled', 'objectClass'], $result);
    }

    /**
     * Test parseFilterAttributes with complex nested filter
     */
    public function testParseFilterAttributesWithComplexNestedFilter()
    {
        // Arrange
        $filter = '(&(objectClass=user)(|(cn=*)(sAMAccountName=*))(&(enabled=true)(department=IT)))';

        // Act
        $result = $this->parser->parseFilterAttributes($filter);

        // Assert
        $this->assertEquals(['cn', 'department', 'enabled', 'objectClass', 'sAMAccountName'], $result);
    }

    /**
     * Test parseFilterAttributes with spaces around operator
     */
    public function testParseFilterAttributesWithSpaces()
    {
        // Arrange - spaces are allowed around the operator but not after opening parenthesis
        $filter = '(cn = Computer01)';

        // Act
        $result = $this->parser->parseFilterAttributes($filter);

        // Assert
        $this->assertEquals(['cn'], $result);
    }

    /**
     * Test parseFilterAttributes with empty filter
     */
    public function testParseFilterAttributesWithEmptyFilter()
    {
        // Arrange
        $filter = '';

        // Act
        $result = $this->parser->parseFilterAttributes($filter);

        // Assert
        $this->assertEquals([], $result);
    }

    /**
     * Test parseFilterAttributes returns sorted array
     */
    public function testParseFilterAttributesReturnsSortedArray()
    {
        // Arrange
        $filter = '(&(zebra=*)(apple=*)(banana=*))';

        // Act
        $result = $this->parser->parseFilterAttributes($filter);

        // Assert
        $this->assertEquals(['apple', 'banana', 'zebra'], $result);
    }

    /**
     * Test parseFilterAttributes with attribute names containing numbers
     */
    public function testParseFilterAttributesWithNumbersInNames()
    {
        // Arrange
        $filter = '(&(attr1=value)(attr2test=value)(test3attr=value))';

        // Act
        $result = $this->parser->parseFilterAttributes($filter);

        // Assert
        $this->assertEquals(['attr1', 'attr2test', 'test3attr'], $result);
    }

    /**
     * Test isValidFilter with valid simple filter
     */
    public function testIsValidFilterWithValidSimpleFilter()
    {
        // Arrange
        $filter = '(cn=Computer01)';

        // Act
        $result = $this->parser->isValidFilter($filter);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isValidFilter with valid complex filter
     */
    public function testIsValidFilterWithValidComplexFilter()
    {
        // Arrange
        $filter = '(&(objectClass=computer)(cn=*)(!(disabled=true)))';

        // Act
        $result = $this->parser->isValidFilter($filter);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isValidFilter with empty filter
     */
    public function testIsValidFilterWithEmptyFilter()
    {
        // Arrange
        $filter = '';

        // Act
        $result = $this->parser->isValidFilter($filter);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isValidFilter with whitespace only filter
     */
    public function testIsValidFilterWithWhitespaceOnly()
    {
        // Arrange
        $filter = '   ';

        // Act
        $result = $this->parser->isValidFilter($filter);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isValidFilter with unbalanced parentheses (more open)
     */
    public function testIsValidFilterWithUnbalancedParenthesesOpen()
    {
        // Arrange
        $filter = '((cn=Computer01)';

        // Act
        $result = $this->parser->isValidFilter($filter);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isValidFilter with unbalanced parentheses (more close)
     */
    public function testIsValidFilterWithUnbalancedParenthesesClose()
    {
        // Arrange
        $filter = '(cn=Computer01))';

        // Act
        $result = $this->parser->isValidFilter($filter);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isValidFilter with no attribute-value pattern
     */
    public function testIsValidFilterWithNoAttributeValuePattern()
    {
        // Arrange
        $filter = '()';

        // Act
        $result = $this->parser->isValidFilter($filter);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isValidFilter with invalid characters
     */
    public function testIsValidFilterWithInvalidCharacters()
    {
        // Arrange
        $filter = '(cn=Test$%^)';

        // Act
        $result = $this->parser->isValidFilter($filter);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isValidFilter with valid special characters
     */
    public function testIsValidFilterWithValidSpecialCharacters()
    {
        // Arrange
        $filter = '(cn=Test-Computer_01.example@domain)';

        // Act
        $result = $this->parser->isValidFilter($filter);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isValidFilter with valid operators
     */
    public function testIsValidFilterWithValidOperators()
    {
        // Arrange
        $filter = '(&(version>=10)(priority<=5)(type~=server)(cn=*))';

        // Act
        $result = $this->parser->isValidFilter($filter);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isValidFilter with wildcards
     */
    public function testIsValidFilterWithWildcards()
    {
        // Arrange
        $filter = '(cn=*Computer*)';

        // Act
        $result = $this->parser->isValidFilter($filter);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isValidFilter with OR operator
     */
    public function testIsValidFilterWithOrOperator()
    {
        // Arrange
        $filter = '(|(cn=Test)(uid=test))';

        // Act
        $result = $this->parser->isValidFilter($filter);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isValidFilter with NOT operator
     */
    public function testIsValidFilterWithNotOperator()
    {
        // Arrange
        $filter = '(!(disabled=true))';

        // Act
        $result = $this->parser->isValidFilter($filter);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isValidFilter with nested filters
     */
    public function testIsValidFilterWithNestedFilters()
    {
        // Arrange
        $filter = '(&(objectClass=user)(|(cn=*)(sAMAccountName=*))(&(enabled=true)(department=IT)))';

        // Act
        $result = $this->parser->isValidFilter($filter);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isValidFilter with backslash escape sequences
     */
    public function testIsValidFilterWithBackslashEscapes()
    {
        // Arrange
        $filter = '(cn=Test\\2AComputer)';

        // Act
        $result = $this->parser->isValidFilter($filter);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isValidFilter with square brackets
     */
    public function testIsValidFilterWithSquareBrackets()
    {
        // Arrange
        $filter = '(cn=[Test])';

        // Act
        $result = $this->parser->isValidFilter($filter);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test extractObjectClasses with single objectClass
     */
    public function testExtractObjectClassesWithSingleClass()
    {
        // Arrange
        $filter = '(objectClass=computer)';

        // Act
        $result = $this->parser->extractObjectClasses($filter);

        // Assert
        $this->assertEquals(['computer'], $result);
    }

    /**
     * Test extractObjectClasses with multiple objectClasses
     */
    public function testExtractObjectClassesWithMultipleClasses()
    {
        // Arrange
        $filter = '(&(objectClass=computer)(objectClass=device)(cn=*))';

        // Act
        $result = $this->parser->extractObjectClasses($filter);

        // Assert
        $this->assertEquals(['computer', 'device'], $result);
    }

    /**
     * Test extractObjectClasses with duplicate objectClasses
     */
    public function testExtractObjectClassesWithDuplicates()
    {
        // Arrange
        $filter = '(&(objectClass=user)(objectClass=user)(cn=*))';

        // Act
        $result = $this->parser->extractObjectClasses($filter);

        // Assert
        $this->assertEquals(['user'], $result);
        $this->assertCount(1, $result);
    }

    /**
     * Test extractObjectClasses excludes wildcards
     */
    public function testExtractObjectClassesExcludesWildcards()
    {
        // Arrange
        $filter = '(&(objectClass=*)(objectClass=computer))';

        // Act
        $result = $this->parser->extractObjectClasses($filter);

        // Assert
        $this->assertEquals(['computer'], $result);
        $this->assertNotContains('*', $result);
    }

    /**
     * Test extractObjectClasses with no objectClass
     */
    public function testExtractObjectClassesWithNoObjectClass()
    {
        // Arrange
        $filter = '(cn=Computer01)';

        // Act
        $result = $this->parser->extractObjectClasses($filter);

        // Assert
        $this->assertEquals([], $result);
    }

    /**
     * Test extractObjectClasses with empty filter
     */
    public function testExtractObjectClassesWithEmptyFilter()
    {
        // Arrange
        $filter = '';

        // Act
        $result = $this->parser->extractObjectClasses($filter);

        // Assert
        $this->assertEquals([], $result);
    }

    /**
     * Test extractObjectClasses with spaces around operator
     */
    public function testExtractObjectClassesWithSpaces()
    {
        // Arrange - spaces are allowed around the operator but not after opening parenthesis
        $filter = '(objectClass = computer)';

        // Act
        $result = $this->parser->extractObjectClasses($filter);

        // Assert
        $this->assertEquals(['computer'], $result);
    }

    /**
     * Test extractObjectClasses filters out empty values
     */
    public function testExtractObjectClassesFiltersOutEmptyValues()
    {
        // Arrange
        $filter = '(&(objectClass=computer)(objectClass=)(objectClass=device))';

        // Act
        $result = $this->parser->extractObjectClasses($filter);

        // Assert
        $this->assertEquals(['computer', 'device'], $result);
        $this->assertCount(2, $result);
    }

    /**
     * Test extractObjectClasses with complex nested filter
     */
    public function testExtractObjectClassesWithComplexFilter()
    {
        // Arrange
        $filter = '(&(|(objectClass=user)(objectClass=contact))(!(objectClass=disabled)))';

        // Act
        $result = $this->parser->extractObjectClasses($filter);

        // Assert
        $this->assertEquals(['user', 'contact', 'disabled'], $result);
    }

    /**
     * Test extractObjectClasses with objectClass in OR statement
     */
    public function testExtractObjectClassesWithOrStatement()
    {
        // Arrange
        $filter = '(|(objectClass=computer)(objectClass=printer))';

        // Act
        $result = $this->parser->extractObjectClasses($filter);

        // Assert
        $this->assertEquals(['computer', 'printer'], $result);
    }

    /**
     * Test extractObjectClasses with objectClass in NOT statement
     */
    public function testExtractObjectClassesWithNotStatement()
    {
        // Arrange
        $filter = '(&(cn=*)(!(objectClass=disabled)))';

        // Act
        $result = $this->parser->extractObjectClasses($filter);

        // Assert
        $this->assertEquals(['disabled'], $result);
    }

    /**
     * Test extractObjectClasses maintains array indexing
     */
    public function testExtractObjectClassesMaintainsProperArrayIndexing()
    {
        // Arrange
        $filter = '(&(objectClass=*)(objectClass=computer)(objectClass=*)(objectClass=device))';

        // Act
        $result = $this->parser->extractObjectClasses($filter);

        // Assert
        $this->assertEquals(['computer', 'device'], $result);
        $this->assertEquals(0, array_keys($result)[0]);
        $this->assertEquals(1, array_keys($result)[1]);
    }

    /**
     * Test parseFilterAttributes returns array type
     */
    public function testParseFilterAttributesReturnsArrayType()
    {
        // Arrange
        $filter = '(cn=Test)';

        // Act
        $result = $this->parser->parseFilterAttributes($filter);

        // Assert
        $this->assertIsArray($result);
    }

    /**
     * Test isValidFilter returns boolean type
     */
    public function testIsValidFilterReturnsBooleanType()
    {
        // Arrange
        $filter = '(cn=Test)';

        // Act
        $result = $this->parser->isValidFilter($filter);

        // Assert
        $this->assertIsBool($result);
    }

    /**
     * Test extractObjectClasses returns array type
     */
    public function testExtractObjectClassesReturnsArrayType()
    {
        // Arrange
        $filter = '(objectClass=computer)';

        // Act
        $result = $this->parser->extractObjectClasses($filter);

        // Assert
        $this->assertIsArray($result);
    }
}