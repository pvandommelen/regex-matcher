<?php


namespace PeterVanDommelen\RegexMatcher;


use PeterVanDommelen\Parser\Expression\ExpressionResultInterface;
use PeterVanDommelen\Parser\Expression\Regex\RegexExpressionResult;
use PeterVanDommelen\Parser\Rewriter\ExpressionResultRewriterInterface;

/**
 * The inner parser result returns null for unmatched groups. Php's preg_match removes them if they are trailing and uses an empty string if not.
 */
class RemoveTrailingEmptyMatchesResultRewriter implements ExpressionResultRewriterInterface
{
    public function reverseRewriteExpressionResult(ExpressionResultInterface $result)
    {
        /** @var RegexExpressionResult $result */
        if ($result instanceof RegexExpressionResult === false) {
            throw new \Exception("Expected a result of type RegexExpressionResult");
        }

        $matches = $result->getMatches();

        $captured_groups = array_slice($matches, 1);
        $last_not_empty = -1;
        foreach ($captured_groups as $i => $match) {
            if ($match !== null) {
                $last_not_empty = $i;
            } else {
                $matches[$i + 1] = "";
            }
        }

        $trimmed_captured_groups = array_slice($matches, 0, $last_not_empty + 2);
        return new RegexExpressionResult($trimmed_captured_groups);
    }

}