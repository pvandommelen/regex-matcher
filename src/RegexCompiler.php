<?php


namespace PeterVanDommelen\RegexMatcher;


use PeterVanDommelen\Parser\Compiler\RewriterCompiler;
use PeterVanDommelen\Parser\Compiler\RewriterParser;
use PeterVanDommelen\Parser\Expression\Alternative\AlternativeExpression;
use PeterVanDommelen\Parser\Expression\Alternative\AlternativeExpressionResult;
use PeterVanDommelen\Parser\Expression\Any\AnyExpression;
use PeterVanDommelen\Parser\Expression\Concatenated\ConcatenatedExpression;
use PeterVanDommelen\Parser\Expression\Concatenated\ConcatenatedExpressionResult;
use PeterVanDommelen\Parser\Expression\Constant\ConstantExpression;
use PeterVanDommelen\Parser\Expression\EndOfString\EndOfStringExpression;
use PeterVanDommelen\Parser\Expression\ExpressionInterface;
use PeterVanDommelen\Parser\Expression\ExpressionResultInterface;
use PeterVanDommelen\Parser\Expression\Named\Grammar;
use PeterVanDommelen\Parser\Expression\Named\NamedExpression;
use PeterVanDommelen\Parser\Expression\Not\NotExpression;
use PeterVanDommelen\Parser\Expression\Repeater\RepeaterExpression;
use PeterVanDommelen\Parser\Expression\Repeater\RepeaterExpressionResult;
use PeterVanDommelen\Parser\Parser\ParserInterface;
use PeterVanDommelen\Parser\ParserHelper;

class RegexCompiler
{

    /**
     * @return ParserInterface
     */
    private function createRegexStringParser() {
        $escape_character = new ConstantExpression("\\");
        $grouping_open = new ConstantExpression("(");
        $grouping_noncapturing = new ConstantExpression("?:");
        $grouping_close = new ConstantExpression(")");
        $any_character = new ConstantExpression(".");
        $zero_or_more = new ConstantExpression("*");
        $one_or_more = new ConstantExpression("+");
        $maybe = new ConstantExpression("?");
        $alternate_character = new ConstantExpression("|");

        $quantifier_open = new ConstantExpression("{");
        $quantifier_seperator = new ConstantExpression(",");
        $quantifier_close = new ConstantExpression("}");

        $escape_sequence = new ConcatenatedExpression(array(
            $escape_character,
            "char" => new AnyExpression(),
        ));

        $character_set_open = new ConstantExpression("[");
        $character_set_open_not = new ConstantExpression("[^");
        $character_set_close = new ConstantExpression("]");

        $character_set_char = new AlternativeExpression(array(
            "escaped" => $escape_sequence,
            "normal" => new NotExpression(new AlternativeExpression(array(
                new ConstantExpression("^"),
                $character_set_close,
                $escape_character,
            )))
        ));

        $not_special = new NotExpression(new AlternativeExpression(array(
            $any_character,
            $grouping_open,
            $escape_character,
            $zero_or_more,
            $one_or_more,
            $maybe,
            $character_set_open,
            $character_set_close,
            $alternate_character,
        )));
        $nothing = new ConstantExpression("");

        $number = new RepeaterExpression(new AlternativeExpression(array(
            new ConstantExpression("0"),
            new ConstantExpression("1"),
            new ConstantExpression("2"),
            new ConstantExpression("3"),
            new ConstantExpression("4"),
            new ConstantExpression("5"),
            new ConstantExpression("6"),
            new ConstantExpression("7"),
            new ConstantExpression("8"),
            new ConstantExpression("9"),
        )), false, 1);

        $quantifier = new ConcatenatedExpression(array(
            $quantifier_open,
            "type" => new AlternativeExpression(array(
                "exact" => $number,
                "only minimum" => new ConcatenatedExpression(array(
                    "minimum" => $number,
                    $quantifier_seperator,
                )),
                "minimum and maximum" => new ConcatenatedExpression(array(
                    "minimum" => $number,
                    $quantifier_seperator,
                    "maximum" => $number,
                ))
            )),
            $quantifier_close
        ));

        $expression = new ConcatenatedExpression(array(
            "left" => new RepeaterExpression(new ConcatenatedExpression(array(
                "pattern" => new AlternativeExpression(array(
                    "escaped" => $escape_sequence,
                    "grouped" => new ConcatenatedExpression(array(
                        $grouping_open,
                        "capturing" => new AlternativeExpression(array(
                            "no" => $grouping_noncapturing,
                            "yes" => new ConstantExpression(""),
                        )),
                        "inner" => new NamedExpression("rule"),
                        $grouping_close
                    )),
                    "character_set_not" => new ConcatenatedExpression(array(
                        $character_set_open_not,
                        "chars" => new RepeaterExpression($character_set_char),
                        $character_set_close
                    )),
                    "character_set" => new ConcatenatedExpression(array(
                        $character_set_open,
                        "chars" => new RepeaterExpression($character_set_char),
                        $character_set_close
                    )),
                    "normal" => $not_special,
                    "any" => $any_character,
                )),
                "quantifier" => new AlternativeExpression(array(
                    "zero_or_more" => $zero_or_more,
                    "one_or_more" => $one_or_more,
                    "maybe" => $maybe,
                    "quantifier" => $quantifier,
                    "none" => $nothing,
                )),
            )), false, 1),
            "alternate" => new RepeaterExpression(new ConcatenatedExpression(array(
                $alternate_character,
                "right" => new NamedExpression("rule"),
            )), false, 0, 1)
        ));

        $grammar = new Grammar(array(
            "rule" => $expression
        ));

        $parser = ParserHelper::compileWithGrammar(new ConcatenatedExpression(array(
            "rule" => new NamedExpression("rule"),
            new EndOfStringExpression(),
        )), $grammar);

        return $parser;
    }

    private function getCharactersFromCharacterSet(RepeaterExpressionResult $result) {
        $chars = array();
        foreach ($result->getResults() as $entry) {
            /** @var AlternativeExpressionResult $entry */
            switch ($entry->getKey()) {
                case "normal":
                    $chars[] = $entry->getResult()->getString();
                    break;
                case "escaped":
                    $chars[] = $entry->getResult()->getPart("char")->getString();
                    break;
                default:
                    throw new \Exception("Unknown state");
            }
        }

        return $chars;
    }

    private function getPatternExpression(ConcatenatedExpressionResult $parsed) {
        /** @var AlternativeExpressionResult $pattern */
        $pattern = $parsed->getPart("pattern");
        switch ($pattern->getKey()) {
            case "normal":
                return new ConstantExpression($pattern->getString());
            case "escaped":
                return new ConstantExpression($pattern->getResult()->getPart("char")->getString());
            case "any":
                return new AnyExpression();
            case "grouped":
                /** @var RepeaterExpressionResult $inner */
                $inner = $pattern->getResult()->getPart("inner");
                return $this->createFromParsedRegularExpression($inner);
            case "character_set":
                /** @var RepeaterExpressionResult $chars_result */
                $chars_result = $pattern->getResult()->getPart("chars");
                $chars = $this->getCharactersFromCharacterSet($chars_result);

                $chars_as_constant_expressions = array();
                foreach ($chars as $char) {
                    $chars_as_constant_expressions[] = new ConstantExpression($char);
                }
                return new AlternativeExpression($chars_as_constant_expressions);
            case "character_set_not":
                /** @var RepeaterExpressionResult $chars_result */
                $chars_result = $pattern->getResult()->getPart("chars");
                $chars = $this->getCharactersFromCharacterSet($chars_result);

                $chars_as_constant_expressions = array();
                foreach ($chars as $char) {
                    $chars_as_constant_expressions[] = new ConstantExpression($char);
                }
                return new NotExpression(new AlternativeExpression($chars_as_constant_expressions));
        }
        throw new \Exception("Unknown case: " . $pattern->getKey());
    }

    private function getRepeaterFromQuantifierType(ExpressionInterface $pattern_expression, AlternativeExpressionResult $quantifier_type) {
        switch ($quantifier_type->getKey()) {
            case "exact":
                $amount = intval($quantifier_type->getResult()->getString());
                return new RepeaterExpression($pattern_expression, false, $amount, $amount);
            case "only minimum":
                $minimum = intval($quantifier_type->getResult()->getPart("minimum")->getString());
                return new RepeaterExpression($pattern_expression, false, $minimum, null);
            case "minimum and maximum":
                $minimum = intval($quantifier_type->getResult()->getPart("minimum")->getString());
                $maximum = intval($quantifier_type->getResult()->getPart("maximum")->getString());
                return new RepeaterExpression($pattern_expression, false, $minimum, $maximum);
        }
        throw new \Exception("Unknown quantifier type: " . $quantifier_type->getKey());
    }
    
    private function createFromParsedRegularExpressionEntry(ConcatenatedExpressionResult $parsed) {
        $pattern_expression = $this->getPatternExpression($parsed);
        switch ($parsed->getPart("quantifier")->getKey()) {
            case "none":
                return $pattern_expression;
            case "zero_or_more":
                return new RepeaterExpression($pattern_expression);
            case "one_or_more":
                return new RepeaterExpression($pattern_expression, false, 1);
            case "maybe":
                return new RepeaterExpression($pattern_expression, false, 0, 1);
            case "quantifier":
                /** @var AlternativeExpressionResult $quantifier_type */
                $quantifier_type = $parsed->getPart("quantifier")->getResult()->getPart("type");
                return $this->getRepeaterFromQuantifierType($pattern_expression, $quantifier_type);
        }
        throw new \Exception("Unknown quantifier case: " . $parsed->getPart("quantifier")->getKey());
    }

    private function createFromOneSide(RepeaterExpressionResult $side) {
        $entries = array();
        foreach ($side->getResults() as $result_entry) {
            $entries[] = $this->createFromParsedRegularExpressionEntry($result_entry);
        }
        return new ConcatenatedExpression($entries);
    }

    private function createFromParsedRegularExpression(ConcatenatedExpressionResult $parsed) {

        /** @var RepeaterExpressionResult $left */
        $left = $parsed->getPart("left");
        $left_expression = $this->createFromOneSide($left);

        /** @var RepeaterExpressionResult $alternate */
        $alternate = $parsed->getPart("alternate");

        $alternatives = array(
            $left_expression
        );
        if (count($alternate->getResults()) > 0) {
            //no alternative
            foreach ($alternate->getResults() as $alternate_entry) {
                /** @var ConcatenatedExpressionResult $alternate_entry */
                /** @var ConcatenatedExpressionResult $right_side */
                $right_side = $alternate_entry->getPart("right");
                $alternatives[] = $this->createFromParsedRegularExpression($right_side);
            }
        }
        return new AlternativeExpression($alternatives);
    }

    /**
     * @param string $string
     * @return ParserInterface
     * @throws InvalidRegularExpressionException
     */
    public function compile($string) {
        /** @var ConcatenatedExpressionResult $parsed_full */
        $parsed_full = $this->createRegexStringParser()->parse($string);
        if ($parsed_full === null) {
            throw new InvalidRegularExpressionException("The supplied regular expression was invalid or unsupported: " . $string);
        }

        /** @var ConcatenatedExpressionResult $parsed */
        $parsed = $parsed_full->getPart("rule");

        $expression = $this->createFromParsedRegularExpression($parsed);

        $parser = ParserHelper::compile($expression);
        $parser = new RewriterParser($parser, new ResultRewriter($parsed));
        $parser = new RewriterParser($parser, new RemoveTrailingEmptyMatchesResultRewriter());

        return $parser;
    }
}