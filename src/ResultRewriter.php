<?php


namespace PeterVanDommelen\RegexMatcher;


use PeterVanDommelen\Parser\Expression\Alternative\AlternativeExpressionResult;
use PeterVanDommelen\Parser\Expression\Concatenated\ConcatenatedExpressionResult;
use PeterVanDommelen\Parser\Expression\ExpressionResultInterface;
use PeterVanDommelen\Parser\Expression\Regex\RegexExpressionResult;
use PeterVanDommelen\Parser\Expression\Repeater\RepeaterExpressionResult;
use PeterVanDommelen\Parser\Rewriter\ExpressionResultRewriterInterface;

class ResultRewriter implements ExpressionResultRewriterInterface
{
    /** @var ConcatenatedExpressionResult */
    private $regex_ast;

    /**
     * @param ConcatenatedExpressionResult $regex_ast
     */
    public function __construct(ConcatenatedExpressionResult $regex_ast)
    {
        $this->regex_ast = $regex_ast;
    }

    public function getMatchesFromPart(ConcatenatedExpressionResult $part, ExpressionResultInterface $result)
    {
        /** @var AlternativeExpressionResult $pattern */
        $pattern = $part->getPart("pattern");
        /** @var AlternativeExpressionResult $quantifier */
        $quantifier = $part->getPart("quantifier");

        switch ($pattern->getKey()) {
            case "normal":
                return array();
            case "escaped":
                return array();
            case "any":
                return array();
            case "grouped":
                $rewriter = new ResultRewriter($pattern->getResult()->getPart("inner"));
                $capturing = $pattern->getResult()->getPart("capturing")->getKey() === "yes";

                switch ($quantifier->getKey()) {
                    case "none":
                        /** @var ConcatenatedExpressionResult $result */
                        $rewritten = $rewriter->reverseRewriteExpressionResult($result);
                        if ($capturing === true) {
                            return $rewritten->getMatches();
                        }
                        return array_slice($rewritten->getMatches(), 1);
                    case "zero_or_more":
                    case "one_or_more":
                    case "maybe":
                        /** @var RepeaterExpressionResult $result */
                        $results = $result->getResults();
                        $result_count = count($results);

                        if ($result_count === 0) {
                            $missing_groups = self::countGroups($pattern->getResult()->getPart("inner"));
                            if ($capturing === true) {
                                $missing_groups += 1;
                            }
                            return array_fill(0, $missing_groups, null);
                        }

                        $rewritten = $rewriter->reverseRewriteExpressionResult($results[$result_count - 1]);
                        if ($capturing === true) {
                            return $rewritten->getMatches();
                        }
                        return array_slice($rewritten->getMatches(), 1);
                }
                throw new \Exception("Unknown quantifier: " . $quantifier->getKey());
            case "character_set":
                return array();
            case "character_set_not":
                return array();
        }
        throw new \Exception("Unknown case: " . $pattern->getKey());
    }

    private static function countGroupsSide(RepeaterExpressionResult $side) {
        $counter = 0;
        foreach ($side->getResults() as $part) {
            /** @var AlternativeExpressionResult $pattern */
            $pattern = $part->getPart("pattern");
            switch ($pattern->getKey()) {
                case "normal":
                case "escaped":
                case "any":
                case "character_set":
                case "character_set_not":
                    break;
                case "grouped":
                    if ($pattern->getResult()->getPart("capturing")->getKey() === "yes") {
                        $counter += 1;
                    }
                    $counter += self::countGroups($pattern->getResult()->getPart("inner"));
                    break;
                default:
                    throw new \Exception("Unknown pattern");
            }
        }
        return $counter;
    }

    private static function countGroups(ConcatenatedExpressionResult $ast) {
        /** @var RepeaterExpressionResult $left_side */
        $left_side = $ast->getPart("left");
        $count = self::countGroupsSide($left_side);

        /** @var RepeaterExpressionResult $alternate */
        $alternate = $ast->getPart("alternate");
        if (count($alternate->getResults()) > 0) {
            foreach ($alternate->getResults() as $entry) {
                $right_side = $entry->getPart("right");
                $count = max($count, self::countGroups($right_side));
            }
        }
        return $count;
    }

    private function getMatchesFromSide(RepeaterExpressionResult $side, ConcatenatedExpressionResult $side_result) {
        /** @var ConcatenatedExpressionResult[] $side_results */
        $side_results = $side->getResults();

        $combined = array("");
        foreach ($side_result->getParts() as $key => $entry) {
            $combined[0] .= $entry->getString();

            $part = $side_results[$key];

            $entry_matches = $this->getMatchesFromPart($part, $entry);
            $combined = array_merge($combined, $entry_matches);
        }
        return $combined;
    }

    /**
     * @param AlternativeExpressionResult|ExpressionResultInterface $result
     * @return RegexExpressionResult
     */
    public function reverseRewriteExpressionResult(ExpressionResultInterface $result)
    {
        /** @var AlternativeExpressionResult $result */
        /** @var ConcatenatedExpressionResult $side_result */
        $side_result = $result->getResult();

        /** @var RepeaterExpressionResult $left_side */
        $left_side = $this->regex_ast->getPart("left");

        if ($result->getKey() === 0) {
            //left hand side
            return new RegexExpressionResult($this->getMatchesFromSide($left_side, $side_result));
        }
        
        $index = $result->getKey() - 1;
        /** @var ConcatenatedExpressionResult $side */
        $side = $this->regex_ast->getPart("alternate")->getResults()[$index]->getPart("right");
        $rewriter = new ResultRewriter($side);

        $right_side_result = $rewriter->reverseRewriteExpressionResult($side_result);
        $right_side_matches = $right_side_result->getMatches();

        $potential_capture_count = self::countGroupsSide($left_side);
        $matches = array_merge(array($right_side_matches[0]), array_fill(0, $potential_capture_count, null), array_slice($right_side_matches, 1));
        return new RegexExpressionResult($matches);
    }

}