<?php


namespace PeterVanDommelen\RegexMatcher;


use PeterVanDommelen\Parser\Expression\Regex\RegexParser;

class RegexesTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getRegexes
     */
    public function testRegex($pattern, $target, $expected_matches)
    {
        $native_result = (new RegexParser($pattern))->parse($target);
        $native_matches = $native_result !== null ? $native_result->getMatches() : null;
        $this->assertEquals($expected_matches, $native_matches, "native: " . $pattern . " :: " . $target);

        $regex_compiler = new RegexCompiler();
        $parser = $regex_compiler->compile($pattern);
        $result = $parser->parse($target);
        $matches = $result !== null ? $result->getMatches() : null;
        $this->assertEquals($expected_matches, $matches, $pattern . " :: " . $target);
    }

    public function getRegexes()
    {
        return array(
            //basic character matching
            array("a", "ab", array("a")),
            array("a", "ba", null),
            array("ab", "ab", array("ab")),

            //any
            array("..", "ab", array("ab")),
            array("..", "abcde", array("ab")),
            array(".", "\n", array("\n")),

            //repeater
            array("a*", "aaab", array("aaa")),
            array("a+", "aaab", array("aaa")),
            array("a+", "baaa", null),

            //quantifiers
            array("a{3}", "aaab", array("aaa")),
            array("a{2,}", "aaab", array("aaa")),
            array("a{2,}", "ab", null),
            array("a{2,3}", "aaab", array("aaa")),
            array("a{2}", "ab", null),

            //quantifier's opening curly brace does not have to be escaped
            array("a{,2}", "aaab", null),
            array("a{,2}", "a{,2}a", array("a{,2}")),
            array("a{}", "a{}a", array("a{}")),
            array("a{a}", "a{a}a", array("a{a}")),

            //grouping
            array("(a)b", "abc", array("ab", "a")),
            array("(a*)b", "aaab", array("aaab", "aaa")),
            array("(a(a*))b", "aaab", array("aaab", "aaa", "aa")),
            array("(a)*b", "aaab", array("aaab", "a")),
            array("(a)*b", "b", array("b")),
            array("(a*)b", "b", array("b", "")),
            array("(a)*(b)", "b", array("b", "", "b")),
            array("(((a)))*(b)", "b", array("b", "", "", "", "b")),
            array("((a))*b", "aaab", array("aaab", "a", "a")),
            array("(ab*)*", "aaab", array("aaab", "ab")),
            array("(a?)a", "a", array("a", "")),
            array("(a)?a", "a", array("a")),
            array("(a?)(a)", "a", array("a", "", "a")),
            array("(a)?(a)", "a", array("a", null, "a")),

            //noncapturing group
            array("(?:a)b", "abc", array("ab")),
            array("(?:(a))b", "abc", array("ab", "a")),
            array("(?:(a))|(b)", "bc", array("b", "", "b")),

            //escaping
            array("\\..", "aa", null),
            array("\\..", ".a", array(".a")),

            //character sets
            array("[ab]", "ab", array("a")),
            array("[ab]", "bc", array("b")),
            array("[^ab]", "ab", null),
            array("[^ab]", "cd", array("c")),
            array("[ab]*", "ab", array("ab")),
            array("[^ab]*", "cd", array("cd")),

            //character set escaping
            array("[\\]]", "]", array("]")),
            array("[\\\\]", "\\", array("\\")),
            array("[.]", ".", array(".")),
            array("[.]", "x", null),

            //alternation
            array("a|b", "ab", array("a")),
            array("(a|b)(a|b)", "ab", array("ab", "a", "b")),
            array("(a|b)*", "ab", array("ab", "b")),
            array("(a|b)(a|b)", "ab", array("ab", "a", "b")),
            array("ab|cd", "acd", null),
            array("[a](b)c*d|e", "abcde", array("abcd", "b")),
            array("[a](b)c*d|e", "e", array("e")),
            array("ab|cd", "ab", array("ab")),
            array("a(b|c)d", "acd", array("acd", "c")),

            //alternation and matches
            array("((a))|((b))", "ab", array("a", "a", "a")),
            array("((a))|((b))", "b", array("b", "", "", "b", "b")),
            array("((a))|((b))", "c", null),
            array("(a|(b))|(c)", "a", array("a", "a")),
            array("(a|(b))|(c)", "b", array("b", "b", "b")),
            array("(a|(b))|(c)", "c", array("c", "", "", "c")),

            //maybe
            array("a?b", "ab", array("ab")),
            array("a?b", "b", array("b")),
            array("a?a", "a", array("a")),
            array("(a?)(a)", "aa", array("aa", "a", "a")),
            array("(a?)(a)", "a", array("a", "", "a")),
            array("(a)?(a)", "aa", array("aa", "a", "a")),
            array("(a)?(a)", "a", array("a", "", "a")),

            //somewhat more complex
            array('"(?:[^"\\\\]|\\\\.)*"', '"ab\"cd\e"', array('"ab\"cd\e"')),
            array('"(?:[^"\\\\]|\\\\.)*"', '"ab\"cd\e', null),

            //basic utf8 support
            array("€", "€", array("€")),
            array(".", "€", array("€")),
            array(".*", "€", array("€")),
            array("[^a]", "€", array("€")),
        );
    }
}
