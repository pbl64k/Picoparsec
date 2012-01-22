<?php

	// A sketch of linear fare parser (Apollo format)

	require_once(dirname(__FILE__).'/30km.php');

	function parse($parser, $string) { print_r($parser(ParseState::mk(StringToCharTokenList::mk($string), ArrayState::mk()))); }

	function airport() { return str(repeat(alpha(), 3, 3)); }

	function carrier() { return str(repeat(alnum(), 2, 2)); }

	function basis() { return str(repeat(alnum(), 1)); }

	function td()
	{
		return seq(array(
				discard(expect('/')),
				str(repeat(alnum(), 1))
				));
	}

	function currency()
	{
		return transform(str(seq(array(
				str(repeat(num())),
				expect('.'),
				str(repeat(num(), 0, 2))
				))), function ($x) { return floatval($x); });
	}

	function destination()
	{
		return arrstseq(array(
				'x' => attempt(transform(expectstr('X/'), K(TRUE))),
				'e' => attempt(transform(expectstr('E/'), K(TRUE))),
				'airport' => airport(),
				));
	}

	function stopover()
	{
		return seq(array(
				discard(expect('S')),
				currency()
				));
	}

	function stopovers()
	{
		return seq(array(
				discard(space()),
				repeat(stopover(), 1)
				));
	}

	function surcharge()
	{
		return seq(array(
				discard(expect('Q')),
				currency()
				));
	}

	function surcharges()
	{
		return seq(array(
				discard(space()),
				repeat(surcharge(), 1)
				));
	}

	function fare()
	{
		return arrstseq(array(
				'type' => choice(space(), expect('M')),
				'fare' => currency(),
				'basis' => basis(),
				'td' => attempt(td()),
				));
	}

	function financial()
	{
		return arrstseq(array(
				'so' => attempt(stopovers()),
				'fs' => attempt(surcharges()),
				'fare' => attempt(fare()),
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

					return array($segment, $st->set(array('departure' => $segment['destination']['airport'])));
				};
	}

	function itinerary()
	{
		return function (IParseState $st)
				{
					$departureParser = airport();

					list($departure, $st) = $departureParser($st);

					$segmentParser = repeat(segment(), 1);

					return $segmentParser($st->set(array('departure' => $departure)));
				};
	}

	$fc = array();

	$fc[] = 'NYC EK X/DXB EK THR Q222.00 172.00XLCHPUS2 EK X/DXB EK NYC Q222.00 172.00XLCHPUS2 PC80.00 NUC868.00END ROE1.0 FARE USD 868.00 TAX 2.50AY TAX 33.40US TAX 5.00XA TAX 4.50XF TAX 7.00XY TAX 5.50YC TAX 22.70IR TOT USD 948.60';

	$parser = itinerary();

	array_map(function ($fc) use($parser) { parse($parser, $fc); }, $fc);

?>
