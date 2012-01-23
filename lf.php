<?php

	// A sketch of linear fare parser (Apollo format)

	error_reporting(E_ALL | E_STRICT);

	require_once(dirname(__FILE__).'/30km.php');

	function airport() { return str(repeat(alpha(), 3, 3)); }

	function carrier() { return str(repeat(alnum(), 2, 2)); }

	function basis() { return str(repeat(alnum(), 1)); }

	function td() { return last(array(expect('/'), str(repeat(alnum(), 1)))); }

	function number($prec = NULL)
	{
		return transform(
				str(seq(array(
						str(repeat(num())),
						expect('.'),
						str(repeat(num(), 0, $prec))
						))),
				function ($x) use($prec)
				{
					return sprintf('%'.(is_null($prec) ? '' : ('.'.strval($prec))).'f', floatval($x));
				}
				);
	}

	function currency() { return number(2); }

	function floatrate() { return number(); }

	function currencycode() { return str(repeat(alpha(), 3, 3)); }

	function taxcode() { return str(repeat(alnum(), 2, 2)); }

	function destination()
	{
		return arrstseq(array(
				'x' => boolattempt(expectstr('X/')),
				'e' => boolattempt(expectstr('E/')),
				'airport' => airport(),
				));
	}

	function ojdeparture()
	{
		return arrstseq(array(
				0 => discard(space()),
				'oj' => cnst(expectstr('/-'), TRUE),
				'airport' => airport(),
				));
	}

	function stopover() { return last(array(expect('S'), currency())); }

	function stopovers() { return last(array(space(), repeat(stopover(), 1))); }

	function surcharge() { return last(array(expect('Q'), currency())); }

	function surcharges() { return last(array(space(), repeat(surcharge(), 1))); }

	function fare()
	{
		return arrstseq(array(
				'type' => last(array(
						attempt(space()),
						choice(array(
								cnst(expect('M'), 'mileage'),
								cnst(nil(), 'routing'),
								)))),
				'fare' => currency(),
				'basis' => basis(),
				'ticketdesignator' => attempt(td()),
				));
	}

	function financial()
	{
		return arrstseq(array(
				'stopovers' => attempt(stopovers()),
				'surcharges' => attempt(surcharges()),
				'fare' => attempt(fare()),
				));
	}

	function segment()
	{
		return function (IParseState $st)
				{
					$segmentParser = arrstseq(array(
							'ojdeparture' => attempt(ojdeparture()),
							0 => discard(space()),
							'carrier' => carrier(),
							1 => discard(space()),
							'destination' => destination(),
							'financial' => financial(),
							), FALSE, FALSE);

					list($segment, $st) = $segmentParser($st);

					return array($segment, $st->set(array('departure' => $segment['destination'])));
				};
	}

	function itinerary()
	{
		return withstate(ArrayState::mk(), function (IParseState $st)
				{
					$departureParser = airport();

					list($departure, $st) = $departureParser($st);

					$segmentParser = repeat(segment(), 1);

					return $segmentParser($st->set(array('departure' => array('airport' => $departure))));
				}, TRUE);
	}

	function extra()
	{
		return seq(array(
				choice(array(
						expectstr('PC'),
						str(seq(array(
								num(),
								expect('S'),
								))),
						)),
				currency(),
				));
	}

	function extras()
	{
		return last(array(space(), repeat(extra(), 1)));
	}

	function roe() { return last(array(space(), expectstr('ROE'), floatrate())); }

	function zp() { return last(array(space(), expectstr('ZP'), space(), repeat(airport(), 1))); }

	function fareend()
	{
		return arrstseq(array(
				0 => discard(space()),
				'currency' => currencycode(),
				'end' => currency(),
				1 => discard(expectstr('END')),
				'roe' => attempt(roe()),
				'zp' => attempt(zp()),
				));
	}

	function faretot()
	{
		return repeat(arrstseq(array(
				0 => discard(space()),
				1 => discard(choice(array(
						expectstr('FARE'),
						expectstr('EQU'),
						))),
				2 => discard(space()),
				'currency' => currencycode(),
				3 => discard(space()),
				'tot' => currency(),
				)));
	}

	function farecalc()
	{
		return arrstseq(array(
				'extra' => attempt(extras()),
				'end' => fareend(),
				'fare' => faretot(),
				));
	}

	function tax()
	{
		return arrstseq(array(
				0 => discard(space()),
				1 => discard(expectstr('TAX')),
				2 => discard(space()),
				'tax' => currency(),
				'type' => taxcode(),
				));
	}

	function taxes()
	{
		return repeat(tax());
	}

	function total()
	{
		return arrstseq(array(
				0 => discard(space()),
				1 => discard(expectstr('TOT')),
				2 => discard(space()),
				'currency' => currencycode(),
				3 => discard(space()),
				'total' => currency(),
				));
	}

	function fareconstruction()
	{
		return arrstseq(array(
				'itinerary' => itinerary(),
				'farecalc' => farecalc(),
				'taxes' => taxes(),
				'total' => total(),
				));
	}

	$fc = array();

	$fc[] = 'NYC EK X/DXB EK THR Q222.00 172.00XLCHPUS2 EK X/DXB EK NYC Q222.00 172.00XLCHPUS2 PC80.00 NUC868.00END ROE1.0 FARE USD 868.00 TAX 2.50AY TAX 33.40US TAX 5.00XA TAX 4.50XF TAX 7.00XY TAX 5.50YC TAX 22.70IR TOT USD 948.60';
	$fc[] = 'SFO LH X/FRA LH ALA 264.50KKNC16S/CN10 /-MOW UA X/WAS UA SFO 223.00LKWNC4N/CN10 PC122.00 NUC609.50END ROE1.0 FARE USD 610.00 TAX 5.00AY TAX 33.40US TAX 5.00XA TAX 9.00XF TAX 7.00XY TAX 5.50YC TAX 8.10DE TAX 24.20RA TAX 8.40RI TAX 6.30UH TAX 465.00YQ TOT USD 1186.90';
	$fc[] = 'PDX US X/PHX US WAS 211.16GXA7NJ6P US X/PHX US PDX 251.16SXA7NJ4P USD462.32END ZP PDXPHXDCAPHX FARE USD 462.32 TAX 10.00AY TAX 34.68US TAX 18.00XF TAX 15.20ZP TOT USD 540.20';
	$fc[] = 'BRU KL X/AMS KL SEL 121.95RPRBE KL X/AMS KL BRU 121.95RPRBE NUC243.90END ROE0.746177 FARE EUR 182.00 EQU USD 232.00 TAX 33.40BE TAX 18.00CJ TAX 16.40RN TAX 5.00VV TAX 24.50BP TAX 440.60YR TOT USD 769.90';
	$fc[] = 'LAX CO X/WAS CO X/MUC LH LWO 261.00LLWBAX OS VIE LX X/ZRH LX LAX 300.00TLXBAX 1S50.00PC90.00 NUC701.00END ROE1.0 FARE USD 701.00 TAX 5.00AY TAX 33.40US TAX 5.00XA TAX 9.00XF TAX 7.00XY TAX 5.50YC TAX 6.80DE TAX 20.90RA TAX 4.90UA TAX 2.00UD TAX 11.00YK TAX 8.80AT TAX 44.60QD TAX 22.80ZY TAX 25.20CH TAX 476.00YQ TOT USD 1388.90';
	$fc[] = 'LAX CO X/WAS CO X/MUC LH LWO M261.00LLWBAX OS VIE LX X/ZRH LX LAX M300.00TLXBAX 1S50.00PC90.00 NUC701.00END ROE1.0 FARE USD 701.00 TAX 5.00AY TAX 33.40US TAX 5.00XA TAX 9.00XF TAX 7.00XY TAX 5.50YC TAX 6.80DE TAX 20.90RA TAX 4.90UA TAX 2.00UD TAX 11.00YK TAX 8.80AT TAX 44.60QD TAX 22.80ZY TAX 25.20CH TAX 476.00YQ TOT USD 1388.90';

	$parser = fareconstruction();

	function parse($parser, $string)
	{
		$res = $parser(ParseState::mk(StringToCharTokenList::mk($string), ArrayState::mk()));

		print_r($res[0]);
	}

	array_map(function ($fc) use($parser) { parse($parser, $fc); }, $fc);

?>
