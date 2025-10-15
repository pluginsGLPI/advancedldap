<?php

/**
 * -------------------------------------------------------------------------
 * advancedldap plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * MIT License
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2025 by the advancedldap plugin team.
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * @link      https://github.com/pluginsGLPI/advancedldap
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Advancedldap\Tests;

use DbTestCase;
use GlpiPlugin\Advancedldap\Services\LdapFilterSanitizer;

/**
 * Test class for LdapFilterSanitizer
 *
 * Tests RFC 4515 compliance for LDAP injection prevention
 * Only tests public methods as per requirements
 */
class LdapFilterSanitizerTest extends DbTestCase
{
    private $sanitizer;

    public function setUp(): void
    {
        parent::setUp();

        // Create sanitizer instance
        $this->sanitizer = new LdapFilterSanitizer();
    }

    /**
     * Test escapeFilterValue with simple metacharacters
     */
    public function testEscapeFilterValueWithBackslash()
    {
        // Arrange
        $input = '\\';

        // Act
        $result = $this->sanitizer->escapeFilterValue($input);

        // Assert
        $this->assertEquals('\\5c', $result);
    }

    /**
     * Test escapeFilterValue with asterisk wildcard
     */
    public function testEscapeFilterValueWithAsterisk()
    {
        // Arrange
        $input = '*';

        // Act
        $result = $this->sanitizer->escapeFilterValue($input);

        // Assert
        $this->assertEquals('\\2a', $result);
    }

    /**
     * Test escapeFilterValue with parentheses
     */
    public function testEscapeFilterValueWithParentheses()
    {
        // Arrange
        $openParen = '(';
        $closeParen = ')';

        // Act
        $resultOpen = $this->sanitizer->escapeFilterValue($openParen);
        $resultClose = $this->sanitizer->escapeFilterValue($closeParen);

        // Assert
        $this->assertEquals('\\28', $resultOpen);
        $this->assertEquals('\\29', $resultClose);
    }

    /**
     * Test escapeFilterValue with null byte
     */
    public function testEscapeFilterValueWithNullByte()
    {
        // Arrange
        $input = "\x00";

        // Act
        $result = $this->sanitizer->escapeFilterValue($input);

        // Assert
        $this->assertEquals('\\00', $result);
    }

    /**
     * Test escapeFilterValue with combined metacharacters
     */
    public function testEscapeFilterValueWithCombinedMetacharacters()
    {
        // Arrange
        $input = 'user*(admin)\\test';

        // Act
        $result = $this->sanitizer->escapeFilterValue($input);

        // Assert
        $this->assertEquals('user\\2a\\28admin\\29\\5ctest', $result);
    }

    /**
     * Test escapeFilterValue with normal string (no metacharacters)
     */
    public function testEscapeFilterValueWithNormalString()
    {
        // Arrange
        $input = 'john.doe@example.com';

        // Act
        $result = $this->sanitizer->escapeFilterValue($input);

        // Assert
        $this->assertEquals('john.doe@example.com', $result);
    }

    /**
     * Test escapeFilterValue with empty string
     */
    public function testEscapeFilterValueWithEmptyString()
    {
        // Arrange
        $input = '';

        // Act
        $result = $this->sanitizer->escapeFilterValue($input);

        // Assert
        $this->assertEquals('', $result);
    }

    /**
     * Test escapeFilterValue with forDN parameter for comma
     */
    public function testEscapeFilterValueForDNWithComma()
    {
        // Arrange
        $input = ',';

        // Act
        $result = $this->sanitizer->escapeFilterValue($input, true);

        // Assert
        $this->assertEquals('\\,', $result);
    }

    /**
     * Test escapeFilterValue with forDN parameter for plus
     */
    public function testEscapeFilterValueForDNWithPlus()
    {
        // Arrange
        $input = '+';

        // Act
        $result = $this->sanitizer->escapeFilterValue($input, true);

        // Assert
        $this->assertEquals('\\+', $result);
    }

    /**
     * Test escapeFilterValue with forDN parameter for quotes
     */
    public function testEscapeFilterValueForDNWithQuotes()
    {
        // Arrange
        $input = '"';

        // Act
        $result = $this->sanitizer->escapeFilterValue($input, true);

        // Assert
        $this->assertEquals('\\"', $result);
    }

    /**
     * Test escapeFilterValue with forDN parameter for angle brackets
     */
    public function testEscapeFilterValueForDNWithAngleBrackets()
    {
        // Arrange
        $inputLess = '<';
        $inputGreater = '>';

        // Act
        $resultLess = $this->sanitizer->escapeFilterValue($inputLess, true);
        $resultGreater = $this->sanitizer->escapeFilterValue($inputGreater, true);

        // Assert
        $this->assertEquals('\\<', $resultLess);
        $this->assertEquals('\\>', $resultGreater);
    }

    /**
     * Test escapeFilterValue with forDN parameter for semicolon
     */
    public function testEscapeFilterValueForDNWithSemicolon()
    {
        // Arrange
        $input = ';';

        // Act
        $result = $this->sanitizer->escapeFilterValue($input, true);

        // Assert
        $this->assertEquals('\\;', $result);
    }

    /**
     * Test escapeFilterValue with forDN parameter for equals
     */
    public function testEscapeFilterValueForDNWithEquals()
    {
        // Arrange
        $input = '=';

        // Act
        $result = $this->sanitizer->escapeFilterValue($input, true);

        // Assert
        $this->assertEquals('\\=', $result);
    }

    /**
     * Test escapeFilterValue with forDN parameter for hash
     */
    public function testEscapeFilterValueForDNWithHash()
    {
        // Arrange
        $input = '#';

        // Act
        $result = $this->sanitizer->escapeFilterValue($input, true);

        // Assert
        $this->assertEquals('\\#', $result);
    }

    /**
     * Test escapeFilterValue with forDN parameter for combined DN string
     */
    public function testEscapeFilterValueForDNWithCombinedString()
    {
        // Arrange
        $input = 'CN=Admin,User+Test';

        // Act
        $result = $this->sanitizer->escapeFilterValue($input, true);

        // Assert
        $this->assertEquals('CN\\=Admin\\,User\\+Test', $result);
    }

    /**
     * Test escapeFilterValue with forDN parameter for leading space
     */
    public function testEscapeFilterValueForDNWithLeadingSpace()
    {
        // Arrange
        $input = ' test';

        // Act
        $result = $this->sanitizer->escapeFilterValue($input, true);

        // Assert
        $this->assertEquals('\\ test', $result);
    }

    /**
     * Test escapeFilterValue with forDN parameter for trailing space
     */
    public function testEscapeFilterValueForDNWithTrailingSpace()
    {
        // Arrange
        $input = 'test ';

        // Act
        $result = $this->sanitizer->escapeFilterValue($input, true);

        // Assert
        $this->assertEquals('test\\ ', $result);
    }

    /**
     * Test escapeFilterValue with forDN parameter for both leading and trailing spaces
     */
    public function testEscapeFilterValueForDNWithBothSpaces()
    {
        // Arrange
        $input = ' test ';

        // Act
        $result = $this->sanitizer->escapeFilterValue($input, true);

        // Assert
        $this->assertEquals('\\ test\\ ', $result);
    }

    /**
     * Test escapeFilterValue with forDN parameter for single space only
     */
    public function testEscapeFilterValueForDNWithSingleSpace()
    {
        // Arrange
        $input = ' ';

        // Act
        $result = $this->sanitizer->escapeFilterValue($input, true);

        // Assert
        // A single space is escaped as leading space: backslash + space
        // RFC 4514 allows both "\ " and "\20" - we use "\ "
        $this->assertEquals('\ ', $result);
    }

    /**
     * Test isValidFilter with simple valid filter
     */
    public function testIsValidFilterWithSimpleFilter()
    {
        // Arrange
        $filter = '(uid=john)';

        // Act
        $result = $this->sanitizer->isValidFilter($filter);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isValidFilter with wildcard
     */
    public function testIsValidFilterWithWildcard()
    {
        // Arrange
        $filter = '(objectClass=*)';

        // Act
        $result = $this->sanitizer->isValidFilter($filter);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isValidFilter with AND operator
     */
    public function testIsValidFilterWithAndOperator()
    {
        // Arrange
        $filter = '(&(uid=john)(mail=*))';

        // Act
        $result = $this->sanitizer->isValidFilter($filter);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isValidFilter with OR operator
     */
    public function testIsValidFilterWithOrOperator()
    {
        // Arrange
        $filter = '(|(uid=john)(uid=jane))';

        // Act
        $result = $this->sanitizer->isValidFilter($filter);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isValidFilter with greater-than-or-equal operator
     */
    public function testIsValidFilterWithGreaterThanOrEqual()
    {
        // Arrange
        $filter = '(cn>=admin)';

        // Act
        $result = $this->sanitizer->isValidFilter($filter);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isValidFilter with less-than-or-equal operator
     */
    public function testIsValidFilterWithLessThanOrEqual()
    {
        // Arrange
        $filter = '(priority<=5)';

        // Act
        $result = $this->sanitizer->isValidFilter($filter);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isValidFilter with approximate match operator
     */
    public function testIsValidFilterWithApproximateMatch()
    {
        // Arrange
        $filter = '(sn~=smith)';

        // Act
        $result = $this->sanitizer->isValidFilter($filter);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isValidFilter with attribute name containing hyphen
     */
    public function testIsValidFilterWithHyphenInAttribute()
    {
        // Arrange
        $filter = '(custom-field=value)';

        // Act
        $result = $this->sanitizer->isValidFilter($filter);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isValidFilter with attribute name containing numbers
     */
    public function testIsValidFilterWithNumbersInAttribute()
    {
        // Arrange
        $filter = '(field123=value)';

        // Act
        $result = $this->sanitizer->isValidFilter($filter);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isValidFilter with complex nested filter
     */
    public function testIsValidFilterWithComplexNestedFilter()
    {
        // Arrange
        $filter = '(&(objectClass=device)(cn=UPS-*)(serialNumber=*)(description=*UPS*))';

        // Act
        $result = $this->sanitizer->isValidFilter($filter);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isValidFilter with multiple levels of nesting
     */
    public function testIsValidFilterWithMultipleLevelsOfNesting()
    {
        // Arrange
        $filter = '(&(|(uid=john)(uid=jane))(&(objectClass=person)(mail=*)))';

        // Act
        $result = $this->sanitizer->isValidFilter($filter);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isValidFilter with unbalanced opening parenthesis
     */
    public function testIsValidFilterWithUnbalancedOpeningParen()
    {
        // Arrange
        $filter = '(uid=john';

        // Act
        $result = $this->sanitizer->isValidFilter($filter);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isValidFilter with unbalanced closing parenthesis
     */
    public function testIsValidFilterWithUnbalancedClosingParen()
    {
        // Arrange
        $filter = 'uid=john)';

        // Act
        $result = $this->sanitizer->isValidFilter($filter);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isValidFilter with too many opening parentheses
     */
    public function testIsValidFilterWithTooManyOpeningParens()
    {
        // Arrange
        $filter = '((uid=john)';

        // Act
        $result = $this->sanitizer->isValidFilter($filter);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isValidFilter with empty parentheses
     */
    public function testIsValidFilterWithEmptyParentheses()
    {
        // Arrange
        $filter = '()';

        // Act
        $result = $this->sanitizer->isValidFilter($filter);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isValidFilter with empty string
     */
    public function testIsValidFilterWithEmptyString()
    {
        // Arrange
        $filter = '';

        // Act
        $result = $this->sanitizer->isValidFilter($filter);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isValidFilter with whitespace only
     */
    public function testIsValidFilterWithWhitespaceOnly()
    {
        // Arrange
        $filter = '   ';

        // Act
        $result = $this->sanitizer->isValidFilter($filter);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isValidFilter with missing attribute name
     */
    public function testIsValidFilterWithMissingAttributeName()
    {
        // Arrange
        $filter = '(=value)';

        // Act
        $result = $this->sanitizer->isValidFilter($filter);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isValidFilter with attribute name starting with number
     */
    public function testIsValidFilterWithAttributeNameStartingWithNumber()
    {
        // Arrange
        $filter = '(123=value)';

        // Act
        $result = $this->sanitizer->isValidFilter($filter);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isValidFilter with orphan closing parenthesis (unbalanced)
     */
    public function testIsValidFilterWithOrphanParentheses()
    {
        // Arrange
        // This filter has unbalanced parentheses (extra closing paren)
        $filter = '(uid=test))';

        // Act
        $result = $this->sanitizer->isValidFilter($filter);

        // Assert
        // Should be rejected due to unbalanced parentheses check
        $this->assertFalse($result);
    }

    /**
     * Test sanitizeFilter with valid simple filter
     */
    public function testSanitizeFilterWithValidSimpleFilter()
    {
        // Arrange
        $filter = '(uid=john)';

        // Act
        $result = $this->sanitizer->sanitizeFilter($filter);

        // Assert
        $this->assertEquals($filter, $result);
    }

    /**
     * Test sanitizeFilter with valid complex filter
     */
    public function testSanitizeFilterWithValidComplexFilter()
    {
        // Arrange
        $filter = '(&(uid=john)(objectClass=person))';

        // Act
        $result = $this->sanitizer->sanitizeFilter($filter);

        // Assert
        $this->assertEquals($filter, $result);
    }

    /**
     * Test sanitizeFilter with filter containing whitespace
     */
    public function testSanitizeFilterWithWhitespace()
    {
        // Arrange
        $filter = '  (uid=john)  ';
        $expected = '(uid=john)';

        // Act
        $result = $this->sanitizer->sanitizeFilter($filter);

        // Assert
        $this->assertEquals($expected, $result);
    }

    /**
     * Test sanitizeFilter with invalid unbalanced filter
     */
    public function testSanitizeFilterWithInvalidUnbalancedFilter()
    {
        // Arrange
        $filter = '(uid=john';

        // Act
        $result = $this->sanitizer->sanitizeFilter($filter);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test sanitizeFilter with empty string
     */
    public function testSanitizeFilterWithEmptyString()
    {
        // Arrange
        $filter = '';

        // Act
        $result = $this->sanitizer->sanitizeFilter($filter);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test sanitizeFilter with empty parentheses
     */
    public function testSanitizeFilterWithEmptyParentheses()
    {
        // Arrange
        $filter = '()';

        // Act
        $result = $this->sanitizer->sanitizeFilter($filter);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test sanitizeDN with simple DN
     */
    public function testSanitizeDNWithSimpleDN()
    {
        // Arrange
        $dn = 'CN=John Doe,DC=example,DC=com';

        // Act
        $result = $this->sanitizer->sanitizeDN($dn);

        // Assert
        $this->assertEquals('CN\\=John Doe\\,DC\\=example\\,DC\\=com', $result);
    }

    /**
     * Test sanitizeDN with DN containing plus sign
     */
    public function testSanitizeDNWithPlusSign()
    {
        // Arrange
        $dn = 'CN=Test+User,OU=Users';

        // Act
        $result = $this->sanitizer->sanitizeDN($dn);

        // Assert
        $this->assertEquals('CN\\=Test\\+User\\,OU\\=Users', $result);
    }

    /**
     * Test sanitizeDN with DN containing quotes
     */
    public function testSanitizeDNWithQuotes()
    {
        // Arrange
        $dn = 'CN="John Doe",DC=example,DC=com';

        // Act
        $result = $this->sanitizer->sanitizeDN($dn);

        // Assert
        $this->assertEquals('CN\\=\\"John Doe\\"\\,DC\\=example\\,DC\\=com', $result);
    }

    /**
     * Test sanitizeDN with DN containing all special characters
     */
    public function testSanitizeDNWithAllSpecialCharacters()
    {
        // Arrange
        $dn = 'CN=Test,+<>;"=#';

        // Act
        $result = $this->sanitizer->sanitizeDN($dn);

        // Assert
        $this->assertEquals('CN\\=Test\\,\\+\\<\\>\\;\\"\\=\\#', $result);
    }

    /**
     * Test sanitizeDN with empty string
     */
    public function testSanitizeDNWithEmptyString()
    {
        // Arrange
        $dn = '';

        // Act
        $result = $this->sanitizer->sanitizeDN($dn);

        // Assert
        $this->assertEquals('', $result);
    }

    /**
     * Test isValidDN with valid simple DN
     */
    public function testIsValidDNWithValidSimpleDN()
    {
        // Arrange
        $dn = 'ou=users,dc=example,dc=com';

        // Act
        $result = $this->sanitizer->isValidDN($dn);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isValidDN with valid complex DN
     */
    public function testIsValidDNWithValidComplexDN()
    {
        // Arrange
        $dn = 'cn=admin,ou=people,dc=example,dc=com';

        // Act
        $result = $this->sanitizer->isValidDN($dn);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isValidDN with DN containing injection attempt (parentheses)
     */
    public function testIsValidDNWithInjectionAttemptParentheses()
    {
        // Arrange
        $dn = 'ou=Equipment,dc=teclib)(objectClass=*';

        // Act
        $result = $this->sanitizer->isValidDN($dn);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isValidDN with DN containing LDAP filter operators
     */
    public function testIsValidDNWithFilterOperators()
    {
        // Arrange
        $dn = 'ou=users,dc=example&(uid=*)';

        // Act
        $result = $this->sanitizer->isValidDN($dn);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isValidDN with empty DN
     */
    public function testIsValidDNWithEmptyDN()
    {
        // Arrange
        $dn = '';

        // Act
        $result = $this->sanitizer->isValidDN($dn);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isValidDN with DN ending with comma
     */
    public function testIsValidDNWithTrailingComma()
    {
        // Arrange
        $dn = 'ou=users,dc=example,dc=com,';

        // Act
        $result = $this->sanitizer->isValidDN($dn);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isValidDN with DN starting with number
     */
    public function testIsValidDNWithAttributeStartingWithNumber()
    {
        // Arrange
        $dn = '123=test,dc=example,dc=com';

        // Act
        $result = $this->sanitizer->isValidDN($dn);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isValidDN with malformed component (missing value)
     */
    public function testIsValidDNWithMissingValue()
    {
        // Arrange
        $dn = 'ou=,dc=example,dc=com';

        // Act
        $result = $this->sanitizer->isValidDN($dn);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isValidDN with component having only whitespace as value
     */
    public function testIsValidDNWithWhitespaceOnlyValue()
    {
        // Arrange
        $dn = 'ou= ,dc=example,dc=com';

        // Act
        $result = $this->sanitizer->isValidDN($dn);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isValidDN with DN containing asterisk (wildcard)
     */
    public function testIsValidDNWithWildcard()
    {
        // Arrange
        $dn = 'ou=users,dc=*,dc=com';

        // Act
        $result = $this->sanitizer->isValidDN($dn);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test LDAP injection prevention - common injection pattern 1
     */
    public function testInjectionPreventionPattern1()
    {
        // Arrange - injection attempt: *))(|(objectClass=*
        $injection = '*))(|(objectClass=*';

        // Act
        $result = $this->sanitizer->escapeFilterValue($injection);

        // Assert
        // RFC 4515: Only \, *, (, ), and \x00 are escaped in filter values
        // The = sign is NOT escaped in filter values (only in DNs per RFC 4514)
        $this->assertEquals('\\2a\\29\\29\\28|\\28objectClass=\\2a', $result);
        $this->assertFalse($this->sanitizer->isValidFilter($result));
    }

    /**
     * Test LDAP injection prevention - common injection pattern 2
     */
    public function testInjectionPreventionPattern2()
    {
        // Arrange - injection attempt: admin)(uid=*)
        $injection = 'admin)(uid=*)';

        // Act
        $result = $this->sanitizer->escapeFilterValue($injection);

        // Assert
        // RFC 4515: = is not escaped in filter values
        $this->assertEquals('admin\\29\\28uid=\\2a\\29', $result);
    }

    /**
     * Test LDAP injection prevention - bypass attempt with backslash
     */
    public function testInjectionPreventionWithBackslashBypass()
    {
        // Arrange - injection attempt using backslash
        $injection = '\\*)(objectClass=*';

        // Act
        $result = $this->sanitizer->escapeFilterValue($injection);

        // Assert
        $this->assertStringContainsString('\\5c', $result);
        $this->assertStringContainsString('\\2a', $result);
        $this->assertStringContainsString('\\29', $result);
        $this->assertStringContainsString('\\28', $result);
    }

    /**
     * Test real-world usage scenario - building safe filter from user input
     */
    public function testRealWorldUsageScenario()
    {
        // Arrange - user input that contains injection attempt
        $userInput = '*)(objectClass=*';

        // Act
        $escapedInput = $this->sanitizer->escapeFilterValue($userInput);
        $safeFilter = "(uid=$escapedInput)";

        // Assert
        // The overall filter structure is valid
        $this->assertTrue($this->sanitizer->isValidFilter($safeFilter));

        // The escaped value contains the literal text but with metacharacters neutralized
        // RFC 4515: = is not escaped in filter values
        $this->assertStringContainsString('\\2a\\29\\28objectClass=\\2a', $safeFilter);

        // Verify that the dangerous metacharacters are escaped (not functional)
        $this->assertStringContainsString('\\2a', $safeFilter); // * is escaped
        $this->assertStringContainsString('\\29', $safeFilter); // ) is escaped
        $this->assertStringContainsString('\\28', $safeFilter); // ( is escaped

        // The word "objectClass" appears but it's neutralized - not a functional LDAP attribute
        // because the parentheses around it are escaped, making it literal text
    }

    /**
     * Test RFC 4515 compliance - example from RFC Section 4
     */
    public function testRFC4515ComplianceExample1()
    {
        // Arrange - RFC 4515 example: parentheses must be escaped
        $input = '()';

        // Act
        $result = $this->sanitizer->escapeFilterValue($input);

        // Assert
        $this->assertEquals('\\28\\29', $result);
    }

    /**
     * Test RFC 4515 compliance - example with text and parentheses
     */
    public function testRFC4515ComplianceExample2()
    {
        // Arrange - RFC 4515 example
        $input = 'Parens R Us (for all your parenthetical needs)';

        // Act
        $result = $this->sanitizer->escapeFilterValue($input);

        // Assert
        $this->assertEquals('Parens R Us \\28for all your parenthetical needs\\29', $result);
    }
}
