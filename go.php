<?php 

function pre_request_bitrix24($request) { // для переопределения массива перед отправкой
	if (isset($request['STATUS_ID']) && $request['STATUS_ID'] == 'нет') {
		$request['STATUS_ID'] = 'JUNK';
	}
	if (isset($request['STATUS_ID']) && !$request['STATUS_ID']) {
		$request['STATUS_ID'] = 'NEW';
	}
	return $request;
}




/// общая инфа
//require_once 'Excel/reader.php'; // для парса xls необходима либа https://github.com/derhasi/phpExcelReader
include_once('lib.php');
$staff = new FilesToB24Leads(false); // если передать тру, то перед выполнением затрутся логи
$staff->bitrix24_url = 'УРЛ.bitrix24.ru';
$staff->bitrix24_login = 'ЛОГИН';
$staff->bitrix24_password = 'ПАС';
$staff->escape_first_string = true; // пропустить первую строчку
$staff->file_folder = 'files/';
//$staff->ignore_hash = true; // не запоминать хэши файлов, отправка произойдет даже если файлы не изменились



/// ЦСВ
$staff->file = 'files/csv.csv';
$staff->csv_delemiter = '|'; // разделитель строчек в цсв
$staff->additional_request = array( // что будет добавляться к каждому лиду
	'SOURCE_ID' => 'OTHER',
	'COMMENTS' => 'Лид из файла: '.$staff->file
);
$staff->matching = array( // соответствие полей в битриксе и полей в файле, если значение в массиве - ячейки из файла будут склеиваться через delemiter https://dev.1c-bitrix.ru/community/blogs/chaos/crm-sozdanie-lidov-iz-drugikh-servisov.php
	'TITLE' => 10,
	'ASSIGNED_BY_ID' => 3,
	'ADDRESS' => array(5,8,13,'delemiter' => ', '),
	'PHONE_OTHER' => 15,
	'PHONE_MOBILE' => 14,
	'EMAIL_WORK' => 16,
	'STATUS_ID' => 4
);
$staff->go(); // запускаем шарманку


// XLS
$staff->file = 'files/xls.xls';
$staff->additional_request = array( // что будет добавляться к каждому лиду
	'SOURCE_ID' => 'OTHER',
	'COMMENTS' => 'Лид из файла: '.$staff->file
);
$staff->matching = array(
	'TITLE' => 10,
	'ASSIGNED_BY_ID' => 3,
	'ADDRESS' => array(5,8,13,'delemiter' => ', '),
	'PHONE_OTHER' => 15, 
	'PHONE_MOBILE' => 14,
	'EMAIL_WORK' => 16,
	'STATUS_ID' => 4
);
$staff->go(); // запускаем шарманку


// GOOGLE TABLE
//12xe1kvJM_rAp2l-ytYWUi4RHys_cEawrdr0vkcq3dGU
// должна быть опубликована в вебе
// https://spreadsheets.google.com/feeds/list/CODE_HERE/od6/public/values?alt=json
$staff->file = 'https://spreadsheets.google.com/feeds/list/ДЛИННЫЙ КОД/od6/public/values?alt=json';
$staff->remote_source = 'google';
$staff->additional_request = array( // что будет добавляться к каждому лиду
	'SOURCE_ID' => 'OTHER',
	'COMMENTS' => 'Лид из гугл таблицы'
);
//$staff->debug = true; // вывести данные и не отправлять
$staff->matching = array(
	'TITLE' => 10,
	'ASSIGNED_BY_ID' => 3,
	'ADDRESS' => array(5,8,13,'delemiter' => ', '),
	'PHONE_OTHER' => 15,
	'PHONE_MOBILE' => 14,
	'EMAIL_WORK' => 16,
	'STATUS_ID' => 4
);
$staff->go(); // запускаем шарманку

 ?>
