
var Picoparsec =
{
	ParseFailureException: function(str)
	{
	},

	TokenExhaustionException: function(str)
	{
	},

	StringToCharTokenList: function(str)
	{
		this.nextToken = function()
		{
			if (str.length == 0)
			{
				throw new Picoparsec.TokenExhaustionException();
			}

			return [str.substring(0, 1), new Picoparsec.StringToCharTokenList(str.substring(1))];
		};
	},

	EmptyState: function()
	{
		this.get = function()
		{
			return null;
		};

		this.set = function(state)
		{
			return this;
		};
	},

	ArrayState: function(arr)
	{
		this.get = function()
		{
			return arr;
		};

		this.set = function(state)
		{
			return new Picoparsec.ArrayState(state);
		};
	},

	ParseState: function(tokenList, state)
	{
		this.nextToken = function()
		{
			var nt = tokenList.nextToken();

			return [nt[0], new Picoparsec.ParseState(nt[1], state)];
		};

		this.get = function()
		{
			return state.get();
		};

		this.set = function(newState)
		{
			return this.inject(state.set(newState));
		};

		this.extrude = function()
		{
			return state;
		};

		this.inject = function(newState)
		{
			return new Picoparsec.ParseState(tokenList, newState);
		};
	},

	I: function(x)
	{
		return x;
	},

	K: function(x)
	{
		return function(y)
				{
					return x;
				};
	},

	nil: function()
	{
		return function(state)
				{
					return [null, state];
				};
	},

	expectf: function(f, desc)
	{
		return function(state)
				{
					var nt;

					try
					{
						nt = state.nextToken();
					}
					catch (e)
					{
						if (e instanceof Picoparsec.TokenExhaustionException)
						{
							throw new Picoparsec.ParseFailureException('Expected {' + desc + '}, ran out of tokens');
						}
						else
						{
							throw e;
						}
					}

					if (f(nt[0]))
					{
						return nt;
					}

					throw new Picoparsec.ParseFailureException('Expected {' + desc + '}, got {' + nt[0] + '}');
				};
	},

	expect: function(token)
	{
		return Picoparsec.expectf(function(t) { return t === token; }, token);
	},

	transform: function(parser, f)
	{
		return function(state)
				{
					var nt = parser(state);

					return [f(nt[0]), nt[1]];
				};
	},

	cnst: function(parser, value)
	{
		return Picoparsec.transform(parser, Picoparsec.K(value));
	},

	discard: function(parser)
	{
		return Picoparsec.cnst(parser, null);
	},

	str: function(parser)
	{
		return Picoparsec.transform(parser, function(x) { return x.reduce(function(a, b) { return a + b; }, ''); });
	},

	expectstr: function(str)
	{
		var arr = [];

		for (var i = 0; i != str.length; ++i)
		{
			arr.push(str[i]);
		}

		return Picoparsec.str(Picoparsec.seq(arr.map(function(chr) { return Picoparsec.expect(chr); }), false));
	},

	attempt: function(parser)
	{
		return function(state)
				{
					try
					{
						var res = parser(state);
						return res;
					}
					catch (e)
					{
						if (e instanceof Picoparsec.ParseFailureException)
						{
							return [null, state];
						}
						else
						{
							throw e;
						}
					}
				};
	},

	boolattempt: function(parser)
	{
		return function(state)
				{
					var p = Picoparsec.attempt(Picoparsec.cnst(parser, true));

					var res = p(state);

					if (res[0] == null)
					{
						return [false, state];
					}
					else
					{
						return res;
					}
				};
	},

	seq: function(parsers, ignoreNulls)
	{
		return function(state)
				{
					var result = [];
					var curState = state;

					for (var ix = 0; ix != parsers.length; ++ix)
					{
						var res = parsers[ix](curState);

						if (! ignoreNulls || res[0] != null)
						{
							result.push(res[0]);
						}

						curState = res[1];
					}

					return [result, curState];
				};
	},

	last: function(parsers, ignoreNulls)
	{
		return function(state)
				{
					var parser = Picoparsec.seq(parsers, ignoreNulls);

					var res = parser(state);

					return [res[0][res[0].length - 1], res[1]];
				};
	},

	choice: function(parsers)
	{
		return function(state)
				{
					var exc = null;

					for (var ix = 0; ix != parsers.length; ++ix)
					{
						try
						{
							var res = parsers[ix](state);
							return res;
						}
						catch (e)
						{
							if (e instanceof Picoparsec.ParseFailureException)
							{
								exc = e;
							}
							else
							{
								throw e;
							}
						}
					}

					throw exc;
				};
	},

	repeatFixed: function(parser, min, max)
	{
		return function(state)
				{
					var result = [];
					var curState = state;

					var res;

					for (var matches = 0; matches != min; ++matches)
					{
						res = parser(curState);

						result.push(res[0]);

						curState = res[1];
					}

					for (; matches != max; ++matches)
					{
						try
						{
							res = parser(curState);

							result.push(res[0]);

							curState = res[1];
						}
						catch (e)
						{
							if (e instanceof Picoparsec.ParseFailureException)
							{
								break;
							}
							else
							{
								throw e;
							}
						}
					}

					return [result, curState];
				};
	},

	repeat: function(parser, min)
	{
		return function(state)
				{
					var result = [];
					var curState = state;

					var res;

					for (var matches = 0; matches != min; ++matches)
					{
						res = parser(curState);

						result.push(res[0]);

						curState = res[1];
					}

					for (; true; ++matches)
					{
						try
						{
							res = parser(curState);

							result.push(res[0]);

							curState = res[1];
						}
						catch (e)
						{
							if (e instanceof Picoparsec.ParseFailureException)
							{
								break;
							}
							else
							{
								throw e;
							}
						}
					}

					return [result, curState];
				};
	},

	space: function()
	{
		return Picoparsec.expect(' ');
	},

	alpha: function()
	{
		return Picoparsec.expectf(function(token) { return /^[A-Za-z]$/.test(token); }, 'alpha');
	},

	num: function()
	{
		return Picoparsec.expectf(function(token) { return /^[0-9]$/.test(token); }, 'num');
	},

	alnum: function()
	{
		return Picoparsec.expectf(function(token) { return /^[0-9A-Za-z]$/.test(token); }, 'alnum');
	},

	withstate: function(scopedState, parser, keepResult)
	{
		return function(state)
				{
					var oldState = state.extrude();

					var res = parser(state.inject(scopedState));

					return [keepResult ? res[0] : res[1].get(), res[1].inject(oldState)];
				};
	},

	arrstseq: function(sequence, keepResult, freshState, ignoreNulls)
	{
		var f = function(state)
		{
			var curState = state;

			//for (var field in sequence)
			for (var field = 0; field != sequence.length; ++field)
			{
				var res = sequence[field](curState);

				curState = res[1];

				var st = curState.get();

				if (! ignoreNulls || res[0] != null)
				{
					st[field] = res[0];
				}

				curState = curState.set(st);
			}

			return [curState.get(), curState];
		};

		return freshState ? Picoparsec.withstate(new Picoparsec.ArrayState([]), f, keepResult) : f;
	}
};

