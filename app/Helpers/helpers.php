<?php

/**
 * File name: helpers.php
 * Last modified: 2022.02.09 at 12:41:52
 * Author: Cesar Chab - https://cesarchabuluac.com
 * Copyright (c) 2022
 */

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * @param $bytes
 * @param int $precision
 * @return string
 */
function formatedSize($bytes, $precision = 1)
{
    $units = array('B', 'KB', 'MB', 'GB', 'TB');

    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= pow(1024, $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * [getMonthNames description]
 *
 * @return  [type]  [return description]
 */
function getMonthNames(){
    return [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ];
}
