<?php


namespace PeterVanDommelen\RegexMatcher;


use PeterVanDommelen\Parser\Expression\Alternative\AlternativeExpression;
use PeterVanDommelen\Parser\Expression\Any\AnyExpression;
use PeterVanDommelen\Parser\Expression\Concatenated\ConcatenatedExpression;
use PeterVanDommelen\Parser\Expression\Constant\ConstantExpression;
use PeterVanDommelen\Parser\Expression\Not\NotExpression;
use PeterVanDommelen\Parser\Expression\Regex\RegexExpression;
use PeterVanDommelen\Parser\Expression\Regex\RegexParser;
use PeterVanDommelen\Parser\Expression\Repeater\RepeaterExpression;
use PeterVanDommelen\Parser\Parser\ParserInterface;
use PeterVanDommelen\Parser\ParserHelper;

class PerformanceTest extends \PHPUnit_Framework_TestCase
{

    private function runParser($n, ParserInterface $parser, $target) {
        $start = microtime(true);
        $result = null;
        for ($i = 0; $i < $n; $i += 1) {
            $result = $parser->parse($target);
        }
        $end = microtime(true);
        $diff_ms = ($end - $start) * 1000;
//        echo sprintf("\n%fms", $diff_ms);
//        echo "\n";
//        var_dump($result);
    }

    private function getEscapedString() {
        return array(
            5000,
            '"(?:[^"\\\\]|\\\\.)*"',
            '"ab\"cd\e"',
        );
    }

    /**
     * This tests the overhead of figuring out the matches array
     */
    public function testEscapedStringHandmade() {
        list($n, $regex, $target) = $this->getEscapedString();

        $quote_expression = new ConstantExpression('"');
        $backslash_expression = new ConstantExpression("\\");
        $expression = new ConcatenatedExpression(array(
            $quote_expression,
            new RepeaterExpression(new AlternativeExpression(array(
                new NotExpression(new AlternativeExpression(array(
                    $quote_expression,
                    $backslash_expression
                ))),
                new ConcatenatedExpression(array(
                    $backslash_expression,
                    new AnyExpression(),
                )),
            ))),
            $quote_expression
        ));

        $parser = ParserHelper::compile($expression);
        $this->runParser($n, $parser, $target);
    }

    public function testEscapedStringRegexExpression() {
        list($n, $regex, $target) = $this->getEscapedString();
        $expression = new RegexExpression($regex);

        $parser = ParserHelper::compile($expression);

        $this->runParser($n, $parser, $target);
    }

    public function testEscapedStringNative() {
        list($n, $regex, $target) = $this->getEscapedString();

        $parser = new RegexParser($regex);
        $this->runParser($n, $parser, $target);
    }

    public function testEscapedString() {
        list($n, $regex, $target) = $this->getEscapedString();

        $compiler = new RegexCompiler();
        $parser = $compiler->compile($regex);
        $this->runParser($n, $parser, $target);
    }

    private function getBacktrack1() {
        return array(
            50,
            '(x+x+)+y',
            str_repeat("x", 25)
        );
    }

    public function testBacktrackNative1() {
        list($n, $regex, $target) = $this->getBacktrack1();

        $parser = new RegexParser($regex);
        $this->runParser($n, $parser, $target);
    }

    public function testBacktrack1() {
        list($n, $regex, $target) = $this->getBacktrack1();

        $compiler = new RegexCompiler();
        $parser = $compiler->compile($regex);
//        var_dump($parser);
        $this->runParser($n, $parser, $target);

//        var_dump(memory_get_peak_usage(true) / 1000000);
    }

    private function getBacktrack2() {
        $order = 10;
        return array(
            50,
            sprintf('(%s)%s', str_repeat("a?", $order), str_repeat("a", $order)),
            str_repeat("a", $order)
        );
    }

    public function testBacktrack2Native() {
        ini_set('pcre.backtrack_limit', PHP_INT_MAX);
        list($n, $regex, $target) = $this->getBacktrack2();

        $parser = new RegexParser($regex);
        $this->runParser($n, $parser, $target);
    }

    public function testBacktrack2() {
        list($n, $regex, $target) = $this->getBacktrack2();

        $compiler = new RegexCompiler();
        $parser = $compiler->compile($regex);
//        var_dump($parser);
        $this->runParser($n, $parser, $target);

//        var_dump(memory_get_peak_usage(true) / 1000000);
    }
}