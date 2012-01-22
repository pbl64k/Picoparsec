<?php

	// A sketch of linear fare parser (Apollo format)

	error_reporting(E_ALL | E_STRICT);

	require_once(dirname(__FILE__).'/30km.php');

	function airport() { return str(repeat(alpha(), 3, 3)); }

	function carrier() { return str(repeat(alnum(), 2, 2)); }

	function basis() { return str(repeat(alnum(), 1)); }

	function td()
	{
		return last(array(expect('/'), str(repeat(alnum(), 1))));
	}

	function currency()
	{
		return transform(
				str(seq(array(
						str(repeat(num())),
						expect('.'),
						str(repeat(num(), 0, 2))
						))),
				function ($x)
				{
					return sprintf('%.2f', floatval($x));
				}
				);
	}

	function destination()
	{
		return arrstseq(array(
				'x' => boolattempt(expectstr('X/')),
				'e' => boolattempt(expectstr('E/')),
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
				'type' => choice(cnst(space(), 'routing'), cnst(expect('M'), 'mileage')),
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
				'fare' => attempt(withstate(ArrayState::mk(), fare())),
				));
	}

	function segment()
	{
		return function (IParseState $st)
				{
					$segmentParser = arrstseq(array(
							0 => discard(space()),
							'carrier' => carrier(),
							1 => discard(space()),
							'destination' => withstate(ArrayState::mk(), destination()),
							'financial' => withstate(ArrayState::mk(), financial()),
							));

					list($segment, $st) = $segmentParser($st);

					return array($segment, $st->set(array('departure' => $segment['destination'])));
				};
	}

	function itinerary()
	{
		return function (IParseState $st)
				{
					$departureParser = airport();

					list($departure, $st) = $departureParser($st);

					$segmentParser = repeat(segment(), 1);

					return $segmentParser($st->set(array('departure' => array('airport' => $departure))));
				};
	}

	$fc = array();

	$fc[] = 'NYC EK X/DXB EK THR Q222.00 172.00XLCHPUS2 EK X/DXB EK NYC Q222.00 172.00XLCHPUS2 PC80.00 NUC868.00END ROE1.0 FARE USD 868.00 TAX 2.50AY TAX 33.40US TAX 5.00XA TAX 4.50XF TAX 7.00XY TAX 5.50YC TAX 22.70IR TOT USD 948.60';

	$parser = itinerary();

	function parse($parser, $string)
	{
		print_r($parser(ParseState::mk(StringToCharTokenList::mk($string), ArrayState::mk())));
	}

	array_map(function ($fc) use($parser) { parse($parser, $fc); }, $fc);

?>
