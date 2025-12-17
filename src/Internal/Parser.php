<?php

declare(strict_types=1);

namespace JsonStream\Internal;

use JsonStream\Config;
use JsonStream\Exception\ParseException;
use JsonStream\Internal\JsonPath\PathEvaluator;

/**
 * Streaming JSON parser using generators
 *
 * Converts token stream to PHP values using generators for memory-efficient
 * streaming parsing. Enforces depth limits to prevent stack overflow.
 *
 * When a PathEvaluator is provided, the parser evaluates path matches during
 * parsing and only yields matching values, enabling true streaming for JSONPath
 * expressions without buffering entire structures in memory.
 *
 * @internal
 */
final class Parser
{
    private int $depth = 0;

    /**
     * @param  Lexer  $lexer  Token source
     * @param  int  $maxDepth  Maximum nesting depth
     * @param  PathEvaluator|null  $pathEvaluator  Optional path evaluator for streaming JSONPath
     */
    public function __construct(
        private readonly Lexer $lexer,
        private readonly int $maxDepth = Config::DEFAULT_MAX_DEPTH,
        private readonly ?PathEvaluator $pathEvaluator = null
    ) {
    }

    /**
     * Parse any JSON value
     *
     * @return mixed Parsed value (scalar, array, or object as associative array)
     *
     * @throws ParseException On invalid JSON
     */
    public function parseValue(): mixed
    {
        $token = $this->lexer->peekToken(); // Peek instead of consume

        return match ($token->type) {
            TokenType::LEFT_BRACE => $this->parseObjectComplete(),
            TokenType::LEFT_BRACKET => $this->parseArrayComplete(),
            TokenType::STRING, TokenType::NUMBER,
            TokenType::TRUE, TokenType::FALSE, TokenType::NULL => $this->lexer->nextToken()->value,
            TokenType::EOF => throw new ParseException('Unexpected end of file'),
            default => throw $this->createException('Unexpected token', $this->lexer->nextToken()),
        };
    }

    /**
     * Parse value and yield all matching subvalues based on path filter
     *
     * Uses true streaming for simple patterns (e.g., $.Ads[*], $.prop.array[*])
     * where it navigates to the target and streams from there.
     *
     * @return \Generator<int, mixed>
     *
     * @throws ParseException On invalid JSON
     */
    public function parseAndExtractMatches(): \Generator
    {
        if ($this->pathEvaluator === null) {
            // No filtering - just parse and yield the whole value
            yield $this->parseValue();

            return;
        }

        // Reset path evaluator to start from root
        $this->pathEvaluator->reset();

        // Check if root matches
        if ($this->pathEvaluator->matches()) {
            yield $this->parseValue();

            return;
        }

        // Try to stream - navigate to target and yield from there
        yield from $this->streamFromPath();
    }

    /**
     * Navigate to path target and stream results
     *
     * For simple patterns like $.Ads[*], navigates to the target array/object
     * and streams elements directly without buffering.
     *
     * @return \Generator<int, mixed>
     */
    private function streamFromPath(): \Generator
    {
        $token = $this->lexer->peekToken();

        if ($token->type === TokenType::LEFT_BRACE) {
            yield from $this->streamFromObject();
        } elseif ($token->type === TokenType::LEFT_BRACKET) {
            yield from $this->streamFromArray();
        }
    }

    /**
     * Navigate through object properties to find streaming target
     *
     * @return \Generator<int, mixed>
     */
    private function streamFromObject(): \Generator
    {
        assert($this->pathEvaluator !== null);
        $this->expectToken(TokenType::LEFT_BRACE);
        $this->increaseDepth();

        if ($this->lexer->peekToken()->type === TokenType::RIGHT_BRACE) {
            $this->lexer->nextToken();
            $this->decreaseDepth();

            return;
        }

        while (true) {
            // Get property name
            $keyToken = $this->lexer->nextToken();
            if ($keyToken->type !== TokenType::STRING) {
                throw $this->createException('Expected string key', $keyToken);
            }

            /** @var string $key */
            $key = $keyToken->value;
            $this->expectToken(TokenType::COLON);

            // Enter this property in evaluator
            $this->pathEvaluator->enterLevel($key, null);

            // Check what type of value this is
            $token = $this->lexer->peekToken();

            if ($token->type === TokenType::LEFT_BRACE) {
                // Nested object - recurse or check if it matches
                if ($this->pathEvaluator->matches()) {
                    // This object itself matches - parse and yield it
                    $value = $this->parseValue();
                    yield $value;
                } else {
                    // Go deeper to find matches
                    yield from $this->streamFromObject();
                }
                $this->pathEvaluator->exitLevel();
            } elseif ($token->type === TokenType::LEFT_BRACKET) {
                // Array - recurse to check elements
                yield from $this->streamFromArray();
                $this->pathEvaluator->exitLevel();
            } else {
                // Scalar value - check if it matches
                if ($this->pathEvaluator->matches()) {
                    $value = $this->parseValue();
                    yield $value;
                } else {
                    // Skip non-matching value
                    $this->skipValue();
                }
                $this->pathEvaluator->exitLevel();
            }

            // Check what's next
            $token = $this->lexer->nextToken();
            if ($token->type === TokenType::RIGHT_BRACE) {
                $this->decreaseDepth();

                return;
            }

            if ($token->type !== TokenType::COMMA) {
                throw $this->createException('Expected comma or closing brace', $token);
            }
        }
    }

    /**
     * Stream through array elements
     *
     * @return \Generator<int, mixed>
     */
    private function streamFromArray(): \Generator
    {
        assert($this->pathEvaluator !== null);
        $this->expectToken(TokenType::LEFT_BRACKET);
        $this->increaseDepth();

        if ($this->lexer->peekToken()->type === TokenType::RIGHT_BRACKET) {
            $this->lexer->nextToken();
            $this->decreaseDepth();

            return;
        }

        $index = 0;       // Array index for path evaluation
        $resultIndex = 0; // Sequential index for yielded results

        while (true) {
            // Enter array index
            $this->pathEvaluator->enterLevel($index, null);

            // Check if filter needs value for evaluation
            $needsValue = $this->pathEvaluator->needsValueForMatch();

            if ($needsValue) {
                // Filter expression - need to parse value to evaluate
                $value = $this->parseValue();
                $this->pathEvaluator->exitLevel();
                $this->pathEvaluator->enterLevel($index, $value);

                if ($this->pathEvaluator->matches()) {
                    // Check if there are remaining segments to extract
                    $remainingSegments = $this->pathEvaluator->getRemainingSegments();
                    if (! empty($remainingSegments)) {
                        // Walk into value to extract remaining path
                        $extracted = $this->pathEvaluator->walkValue($value, $remainingSegments);
                        if ($extracted !== null) {
                            yield $resultIndex++ => $extracted;
                        }
                    } else {
                        yield $resultIndex++ => $value;
                    }
                }

                $this->pathEvaluator->exitLevel();
            } else {
                // No filter - check if current position matches structurally
                $matchesCurrent = $this->pathEvaluator->matches();
                $shouldExtract = $this->pathEvaluator->shouldExtractFromValue();

                if ($shouldExtract) {
                    // We're at an array element that partially matches, and there are
                    // remaining segments to extract (e.g., $.users[*].name)
                    $value = $this->parseValue();
                    $remainingSegments = $this->pathEvaluator->getRemainingSegments();
                    $extracted = $this->pathEvaluator->walkValue($value, $remainingSegments);
                    if ($extracted !== null) {
                        yield $resultIndex++ => $extracted;
                    }
                    $this->pathEvaluator->exitLevel();
                } elseif ($matchesCurrent) {
                    // This element fully matches - parse and yield it
                    $value = $this->parseValue();
                    yield $resultIndex++ => $value;
                    $this->pathEvaluator->exitLevel();
                } else {
                    // Check if we need to go deeper
                    $token = $this->lexer->peekToken();
                    if ($token->type === TokenType::LEFT_BRACE) {
                        yield from $this->streamFromObject();
                        $this->pathEvaluator->exitLevel();
                    } elseif ($token->type === TokenType::LEFT_BRACKET) {
                        yield from $this->streamFromArray();
                        $this->pathEvaluator->exitLevel();
                    } else {
                        // Skip this element
                        $this->skipValue();
                        $this->pathEvaluator->exitLevel();
                    }
                }
            }

            $index++;

            // Check for end or comma
            $token = $this->lexer->nextToken();
            if ($token->type === TokenType::RIGHT_BRACKET) {
                $this->decreaseDepth();

                return;
            }

            if ($token->type !== TokenType::COMMA) {
                throw $this->createException('Expected comma or closing bracket', $token);
            }
        }
    }

    /**
     * Parse array and yield elements progressively
     *
     * Consumes opening bracket and yields values.
     * Yields values only (not keys). Keys are sequential integers starting at 0.
     *
     * @return \Generator<int, mixed>
     *
     * @throws ParseException On invalid JSON
     */
    public function parseArray(): \Generator
    {
        $this->expectToken(TokenType::LEFT_BRACKET); // Consume opening bracket
        $this->increaseDepth();

        // Check for empty array
        if ($this->lexer->peekToken()->type === TokenType::RIGHT_BRACKET) {
            $this->lexer->nextToken(); // consume ]
            $this->decreaseDepth();

            return;
        }

        $index = 0;

        while (true) {
            // Parse and yield value
            yield $this->parseValue();
            $index++;

            // Expect comma or closing bracket
            $token = $this->lexer->nextToken();

            if ($token->type === TokenType::RIGHT_BRACKET) {
                $this->decreaseDepth();

                return;
            }

            if ($token->type !== TokenType::COMMA) {
                throw $this->createException('Expected comma or closing bracket', $token);
            }

            // Check for trailing comma (not allowed in strict mode)
            // @phpstan-ignore identical.alwaysFalse
            if ($this->lexer->peekToken()->type === TokenType::RIGHT_BRACKET) {
                throw $this->createException('Trailing comma not allowed', $token);
            }
        }
    }

    /**
     * Parse object and yield key-value pairs progressively
     *
     * Consumes opening brace and yields key-value pairs.
     * Yields values only (not keys). Keys are available via for each iteration.
     *
     * @return \Generator<string, mixed>
     *
     * @throws ParseException On invalid JSON
     */
    public function parseObject(): \Generator
    {
        $this->expectToken(TokenType::LEFT_BRACE); // Consume opening brace
        $this->increaseDepth();

        // Check for empty object
        if ($this->lexer->peekToken()->type === TokenType::RIGHT_BRACE) {
            $this->lexer->nextToken(); // consume }
            $this->decreaseDepth();

            return;
        }

        while (true) {
            // Expect string key
            $keyToken = $this->lexer->nextToken();
            if ($keyToken->type !== TokenType::STRING) {
                throw $this->createException('Expected string key', $keyToken);
            }

            /** @var string $key */
            $key = $keyToken->value;

            // Expect colon
            $this->expectToken(TokenType::COLON);

            // Parse and yield value with key
            yield $key => $this->parseValue();

            // Expect comma or closing brace
            $token = $this->lexer->nextToken();

            if ($token->type === TokenType::RIGHT_BRACE) {
                $this->decreaseDepth();

                return;
            }

            if ($token->type !== TokenType::COMMA) {
                throw $this->createException('Expected comma or closing brace', $token);
            }

            // Check for trailing comma (not allowed in strict mode)
            // @phpstan-ignore identical.alwaysFalse
            if ($this->lexer->peekToken()->type === TokenType::RIGHT_BRACE) {
                throw $this->createException('Trailing comma not allowed', $token);
            }
        }
    }

    /**
     * Skip a value without parsing it
     *
     * Efficient for skipping large structures when filtering.
     *
     * @throws ParseException On invalid JSON
     */
    public function skipValue(): void
    {
        $token = $this->lexer->nextToken();

        match ($token->type) {
            TokenType::LEFT_BRACE => $this->skipObject(),
            TokenType::LEFT_BRACKET => $this->skipArray(),
            TokenType::STRING, TokenType::NUMBER,
            TokenType::TRUE, TokenType::FALSE, TokenType::NULL => null, // Already consumed
            TokenType::EOF => throw $this->createException('Unexpected end of file', $token),
            default => throw $this->createException('Unexpected token', $token),
        };
    }

    /**
     * Get current nesting depth
     *
     * @return int Current depth
     */
    public function getDepth(): int
    {
        return $this->depth;
    }

    /**
     * Parse complete array into memory (non-streaming)
     *
     * @return array<int, mixed>
     *
     * @throws ParseException On invalid JSON
     */
    private function parseArrayComplete(): array
    {
        $result = [];
        foreach ($this->parseArray() as $value) {
            $result[] = $value;
        }

        return $result;
    }

    /**
     * Parse complete object into memory (non-streaming)
     *
     * @return array<string, mixed>
     *
     * @throws ParseException On invalid JSON
     */
    private function parseObjectComplete(): array
    {
        $result = [];
        foreach ($this->parseObject() as $key => $value) {
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Skip array without parsing values
     *
     * @throws ParseException On invalid JSON
     */
    private function skipArray(): void
    {
        $this->increaseDepth();

        // Check for empty array
        if ($this->lexer->peekToken()->type === TokenType::RIGHT_BRACKET) {
            $this->lexer->nextToken();
            $this->decreaseDepth();

            return;
        }

        while (true) {
            $this->skipValue();

            $token = $this->lexer->nextToken();

            if ($token->type === TokenType::RIGHT_BRACKET) {
                $this->decreaseDepth();

                return;
            }

            if ($token->type !== TokenType::COMMA) {
                throw $this->createException('Expected comma or closing bracket', $token);
            }
        }
    }

    /**
     * Skip object without parsing values
     *
     * @throws ParseException On invalid JSON
     */
    private function skipObject(): void
    {
        $this->increaseDepth();

        // Check for empty object
        if ($this->lexer->peekToken()->type === TokenType::RIGHT_BRACE) {
            $this->lexer->nextToken();
            $this->decreaseDepth();

            return;
        }

        while (true) {
            // Skip key (must be string)
            $keyToken = $this->lexer->nextToken();
            if ($keyToken->type !== TokenType::STRING) {
                throw $this->createException('Expected string key', $keyToken);
            }

            // Expect colon
            $this->expectToken(TokenType::COLON);

            // Skip value
            $this->skipValue();

            // Check what's next
            $token = $this->lexer->nextToken();

            if ($token->type === TokenType::RIGHT_BRACE) {
                $this->decreaseDepth();

                return;
            }

            if ($token->type !== TokenType::COMMA) {
                throw $this->createException('Expected comma or closing brace', $token);
            }
        }
    }

    /**
     * Expect specific token type
     *
     * @param  TokenType  $expected  Expected token type
     * @return Token The expected token
     *
     * @throws ParseException If token doesn't match
     */
    private function expectToken(TokenType $expected): Token
    {
        $token = $this->lexer->nextToken();

        if ($token->type !== $expected) {
            throw $this->createException(
                sprintf('Expected %s, got %s', $expected->name, $token->type->name),
                $token
            );
        }

        return $token;
    }

    /**
     * Increase nesting depth with validation
     *
     * @throws ParseException If max depth exceeded
     */
    private function increaseDepth(): void
    {
        $this->depth++;

        if ($this->depth > $this->maxDepth) {
            throw new ParseException(
                sprintf('Maximum nesting depth of %d exceeded', $this->maxDepth)
            );
        }
    }

    /**
     * Decrease nesting depth
     */
    private function decreaseDepth(): void
    {
        $this->depth--;
    }

    /**
     * Peek at next token without consuming it
     *
     * @internal For use by reader iterators
     */
    public function peekToken(): Token
    {
        return $this->lexer->peekToken();
    }

    /**
     * Create ParseException with token context
     *
     * Token positions are already 1-based from lexer.
     *
     * @param  string  $message  Error message
     * @param  Token  $token  Token that caused error
     * @return ParseException Exception with position
     */
    private function createException(string $message, Token $token): ParseException
    {
        $exception = new ParseException($message);
        $exception->setPosition($token->line, $token->column); // Already 1-based

        return $exception;
    }
}
