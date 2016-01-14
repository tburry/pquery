<?php

/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2016 Vanilla Forums Inc.
 * @license LGPLv2
 */

/**
 * Contains tests for github issues.
 */
class IssueTest extends pQueryTestCase {
    /**
     * Test attributes with no quotes.
     *
     * @link https://github.com/tburry/pquery/issues/4
     */
    public function testAttrWithoutQuotes() {
        $html = '<a href=/index.php/example>Example</a>';
        $dom = pQuery::parseStr($html);

        $this->assertSame('/index.php/example', $dom->query('a')->attr('href'));
    }
}
