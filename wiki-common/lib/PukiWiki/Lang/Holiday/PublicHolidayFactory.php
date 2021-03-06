<?php
namespace PukiWiki\Lang\Holiday;

use PukiWiki\Lang\Holiday\HolidayJP;
use PukiWiki\Lang\Holiday\HolidayUS;

class PublicHolidayFactory{
	// 休日判定
	public static function factory($country='JP', $y,$m,$d)
	{
		switch ($country) {
		case 'JP':
			$obj = new HolidayJP($y,$m,$d);
			break;
		case 'US':
			$obj = new HolidayUS($y,$m,$d);
			break;
		default:
			$obj = new HolidayJP($y,$m,$d);
		}

		$obj->set();
		return $obj->getRecursive();
	}
}