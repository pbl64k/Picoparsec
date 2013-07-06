<?php

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

?>
