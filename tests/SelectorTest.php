<?php

class SelectorTest extends pQueryTestCase {
    public function testAttributeContainsPrefix() {
        $dom = $this->getDom();

        $q = $dom->query('[lang|=en]');
        $this->assertEquals(2, $q->count());
    }

    public function testId() {
        $dom = $this->getDom();

        $this->assertEquals('body', $dom->query('#body')->attr('id'));
        $this->assertEquals('body', $dom->query('body#body')->attr('id'));

        $this->assertEquals('moon-base', $dom->query('#house')->val());
    }

    /// Helpers ///

    /**
     *
     * @return pQuery\DomNode
     */
    protected function getDom() {
        $dom = pQuery::parseFile(__DIR__.'/test-file.html');
        return $dom;
    }
}

