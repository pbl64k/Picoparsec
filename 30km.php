<?php

	// 30km ~ 1 picoparsec

	function I($x) { return $x; }

	function K($x) { return function ($y) use($x) { return $x; }; }

	final class ParseFailureException extends Exception
	{
	}

	final class TokenExhaustionException extends Exception
	{
	}

	interface ITokenList
	{
		public function nextToken();
	}

	final class StringToCharTokenList implements ITokenList
	{
		private $str = NULL;

		final static public function mk($str)
		{
			return new self($str);
		}

		final public function nextToken()
		{
			if (! strlen($this->str))
			{
				throw new TokenExhaustionException;
			}

			return array(substr($this->str, 0, 1), self::mk(substr($this->str, 1)));
		}

		final private function __construct($str)
		{
			$this->str = $str;
		}
	}

	interface IState
	{
		public function get();
		public function set($state);
	}

	final class EmptyState implements IState
	{
		final static public function mk()
		{
			return new self;
		}

		final public function get()
		{
			return NULL;
		}

		final public function set($state)
		{
			return $this;
		}

		final private function __construct()
		{
		}
	}

	final class ArrayState implements IState
	{
		private $arr = array();

		final static public function mk($arr = array())
		{
			return new self($arr);
		}

		final public function get()
		{
			return $this->arr;
		}

		final public function set($state)
		{
			return self::mk($state);
		}

		final private function __construct($arr)
		{
			$this->arr = $arr;
		}
	}

	interface IParseState extends ITokenList, IState
	{
		public function extrude();
		public function inject(IState $state);
	}

	final class ParseState implements IParseState
	{
		private $tokenList = NULL;
		private $state = NULL;

		final static public function mk(ITokenList $tokenList, IState $state)
		{
			return new self($tokenList, $state);
		}

		final public function nextToken()
		{
			list($token, $tokenList) = $this->tokenList->nextToken();

			return array($token, self::mk($tokenList, $this->state));
		}

		final public function get()
		{
			return $this->state->get();
		}

		final public function set($state)
		{
			return $this->inject($this->state->set($state));
		}

		final public function extrude()
		{
			return $this->state;
		}

		final public function inject(IState $state)
		{
			return self::mk($this->tokenList, $state);
		}

		final private function __construct(ITokenList $tokenList, IState $state)
		{
			$this->tokenList = $tokenList;
			$this->state = $state;
		}
	}

	function nil()
	{
		return function (IParseState $state)
				{
					return array(NULL, $state);
				};
	}

	function expectf($f, $desc)
	{
		return function (IParseState $state) use($f, $desc)
				{
					try
					{
						list($nextToken, $remainingTokens) = $state->nextToken();
					}
					catch (TokenExhaustionException $e)
					{
						throw new ParseFailureException('Expected {'.$desc.'}, ran out of tokens');
					}

					if ($f($nextToken))
					{
						return array($nextToken, $remainingTokens);
					}

					throw new ParseFailureException('Expected {'.$desc.'}, got {'.$nextToken.'}');
				};
	}

	function expect($token)
	{
		return expectf(function ($t) use($token) { return $t === $token; }, $token);
	}

	function transform($parser, $f)
	{
		return function (IParseState $state) use($parser, $f)
				{
					list($result, $state) = $parser($state);

					return array($f($result), $state);
				};
	}

	function cnst($parser, $value)
	{
		return transform($parser, K($value));
	}

	function discard($parser)
	{
		return cnst($parser, NULL);
	}

	function str($parser)
	{
		return transform($parser, function ($x) { return implode('', $x); });
	}

	function expectstr($str)
	{
		return str(seq(array_map(function ($char) { return expect($char); }, str_split($str))));
	}

	function attempt($parser)
	{
		return function (IParseState $state) use($parser)
				{
					try
					{
						return $parser($state);
					}
					catch (ParseFailureException $e)
					{
						return array(NULL, $state);
					}
				};
	}

	function boolattempt($parser)
	{
		return function (IParseState $state) use($parser)
				{
					$p = attempt(cnst($parser, TRUE));

					list($result, $state) = $p($state);

					if (is_null($result))
					{
						return array(FALSE, $state);
					}

					return array($result, $state);
				};
	}

	function seq(array $parsers, $ignoreNulls = TRUE)
	{
		return function (IParseState $state) use($parsers, $ignoreNulls)
				{
					$result = array();

					foreach ($parsers as $p)
					{
						list($res, $state) = $p($state);

						if ((! $ignoreNulls) || (! is_null($res)))
						{
							$result[] = $res;
						}
					}

					return array($result, $state);
				};
	}

	function last(array $parsers, $ignoreNulls = TRUE)
	{
		return function (IParseState $state) use($parsers, $ignoreNulls)
				{
					$parser = seq($parsers, $ignoreNulls);

					list($res, $state) = $parser($state);

					return array(array_pop($res), $state);
				};
	}

	function choice(array $parsers)
	{
		return function(IParseState $state) use($parsers)
				{
					$exc = NULL;

					foreach ($parsers as $p)
					{
						try
						{
							return $p($state);
						}
						catch (ParseFailureException $e)
						{
							$exc = $e;
						}
					}

					throw $exc;
				};
	}

	function repeat($parser, $min = 0, $max = NULL)
	{
		return function (IParseState $state) use($parser, $min, $max)
				{
					$result = array();

					for ($matches = 0; $matches != $min; ++$matches)
					{
						list($result[], $state) = $parser($state);
					}

					for (; is_null($max) || ($matches != $max); ++$matches)
					{
						try
						{
							list($result[], $state) = $parser($state);
						}
						catch (ParseFailureException $e)
						{
							break;
						}
					}

					return array($result, $state);
				};
	}

	function space()
	{
		return expect(' ');
	}

	function alpha()
	{
		return expectf(function ($token) { return preg_match('/[A-Za-z]/', $token); }, 'alpha');
	}

	function num()
	{
		return expectf(function ($token) { return preg_match('/[0-9]/', $token); }, 'num');
	}

	function alnum()
	{
		return expectf(function ($token) { return preg_match('/[0-9A-Za-z]/', $token); }, 'alnum');
	}

	function withstate(IState $scopedState, $parser, $keepResult = FALSE)
	{
		return function (IParseState $state) use($scopedState, $parser, $keepResult)
				{
					$oldState = $state->extrude();

					list($result, $newState) = $parser($state->inject($scopedState));

					return array($keepResult ? $result : $newState->get(), $newState->inject($oldState));
				};
	}

	function arrstseq(array $sequence, $keepResult = TRUE, $freshState = TRUE, $ignoreNulls = TRUE)
	{
		$f = function (IParseState $state) use($sequence, $ignoreNulls)
				{
					foreach ($sequence as $field => $p)
					{
						list($result, $state) = $p($state);

						$st = $state->get();

						if ((! $ignoreNulls) || (! is_null($result)))
						{
							$st[$field] = $result;
						}

						$state = $state->set($st);
					}

					return array($state->get(), $state);
				};

		return $freshState ? withstate(ArrayState::mk(), $f, $keepResult) : $f;
	}

?>
