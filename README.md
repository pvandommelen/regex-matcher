A regular expression matcher implemented in PHP.

Usage:

    $regex_compiler = new RegexCompiler();

    $parser = $regex_compiler->compile("a(b)");

    $result = $parser->parse("c");
    $result; // null

    $result = $parser->parse("abc");
    $result->getString(); // "ab"
    $result->getMatches(); // array("ab", "b")

Patterns are always matched at the start of the string, the any symbol (.) will match newlines and will only work with UTF-8 strings. This regex matcher should therefore be equivalent to:

    $pattern = "..";
    $target = "..";
    preg_match("/^$pattern/su", $target, $matches);

##Supported features
- Alternation: expr|expr
- Grouping: (expr)
- Noncapturing grouping: (?:expr)
- Character sets: [ab]
- Excluded character sets: [^ab]
- Any character: .
- Maybe: expr?
- Repeating zero or more: expr*
- Repeating one or more: expr*
- Repeating using a quantifier: expr{2}, expr{2,5} or expr{2,}

##Performance
Bad.

##Why should I use this?
There is no reason to use this. It is slower, has less features and is less tested than the native solution.

##Can I extend it? Or only use a subset of the regex features?
Currently not possible.