<?php 
	error_reporting(E_ALL);
	set_time_limit(0);

	class FilesToB24Leads {
		
		public $bitrix24_url = '';
		public $bitrix24_login = '';
		public $bitrix24_password = '';

		public $file_folder = '';
		public $file = '';
		public $filename = '';
		public $extension = '';

		public $log_file = "log.txt";
		public $hash_file = "hashes.json";

		public $ignore_hash = false;
		public $escape_first_string = false;
		public $method = 'POST';

		public $csv_delemiter = ';';
		public $csv_string_length = 9999999;

		public $matching = array();
		public $additional_request = array();
		public $debug = false;

		public $remote_source = false;

		private $is_file_remote = false;

		private $hashes;

		function __construct($delete = false) {

			if ($delete) $this->delete_tmp();

			$this->log('Запускаемся', '================================');

			if (!file_exists($this->log_file)) {
				$fp = @fopen($this->log_file, "wb");
				if ($fp === FALSE) die('Не удается создать файл лога.');
				fclose($fp);
			}

			if (!file_exists($this->hash_file)) {
				$fp = @fopen($this->hash_file, "wb");
				if ($fp === FALSE) die('Не удается создать файл хэша.');
				fclose($fp);
			}

			// realpath необходим чтобы записи в файлы работали в деструкторе
			$this->log_file = realpath($this->log_file);
			$this->hash_file = realpath($this->hash_file);

			$this->hashes = $this->get_hashes();
		}

		function __destruct() {
			$this->save_hashes();
	       	$this->log('Сворачиваемся, сохраняем хэши.', '================================');
	       	echo '<br>я все.';
	   	}

		public function go() {

			if (!$this->filename) $this->filename = basename($this->file);

			if (!$this->get_file()) {
				$this->log('Ошибка', 'Файл '.$this->file.' плохой. Умираем.');
				return;
			}

			$this->extension = pathinfo($this->file, PATHINFO_EXTENSION);

			$method = 'parse_'.$this->extension;

			if ( !method_exists($this, $method) ) {
				$this->log('Ошибка', 'Не существует метода для парсинга расширения файла '.$this->file.'');
				return;
			}

			$data = call_user_func( array($this, $method) );

			if (!$this->bitrix24_url || !$this->bitrix24_login || !$this->bitrix24_password) {
				$this->log('Ошибка', 'Не заданы все параметры bitrix24_url, bitrix24_login, bitrix24_password');
				return;
			}

			if (!$this->matching) {
				$this->log('Ошибка', 'Не указан массив соответсвия '.$this->file.' Умираем.');
				return;
			}

			if ($this->debug) {
				echo '<pre>';
				print_r($data);
				echo '</pre>';
				die();
			}

			foreach ($data as $row) {
				$request = $this->prepare_request($row);
				$this->send_to_bitrix24($request);
			}

			$this->log('Нотис', 'Отправка лидов из файла '.$this->file.' Завершена. ----------');

		}

		private function prepare_request($row) {
			$request = array();
			foreach ($this->matching as $bitrix_key => $value) {

				$request[$bitrix_key] = '';

				if (is_array($value)) {
					if (isset($value['delemiter'])) {
						$delemiter = $value['delemiter'];
						unset($value['delemiter']);
					} else {
						$delemiter = ' ';
					}

					$vals = array();
					foreach ($value as $el_key => $el) {
						if ( !isset($row[$el]) ) {
							$this->log('Ошибка', 'В строке из файла не найден элемент с ключом: '.$el.'!');
							$row[$el] = '';
							// unset($value[$el_key]);
							// continue;
						}
						$vals[] = $row[$el];
					}

					$request[$bitrix_key] = implode($delemiter, $vals);
					continue;
				}

				if ( !isset($row[$value])) {
					$this->log('Ошибка', 'В строке из файла не найден элемент с ключом: '.$value.'!');
					// continue;
					$row[$value] = '';
				}

				$request[$bitrix_key] = $row[$value];
			}

			return $request;

		}

		private function send_to_bitrix24($request) {
			$request = array_merge($request, $this->additional_request);

			if (function_exists('pre_request_bitrix24')) {
				$request = pre_request_bitrix24($request);
			}

			if (!isset($request['TITLE'])) {
				$this->log('Ошибка', 'Не существует элемента с ключом TITLE, умираем.');
				return;
			}
			if (!$request['TITLE']) {
				$this->log('Ошибка', 'Поле TITLE - пустое, умираем.');
				return;
			}

			$result = $this->go_request($request);

			if ($result === FALSE) { 
				$this->log('Ошибка', 'Непредвиденная ошибка отправки запроса для элемента: '.$request['TITLE']);
				return; 
			}

			$this->log('Нотис', 'Лид '.$request['TITLE'].' отправлен в битрикс. Ответ сервера:'.$result );

			return; 
		}

		private function go_request($request) {

			$request['LOGIN'] = $this->bitrix24_login;
			$request['PASSWORD'] = $this->bitrix24_password;

			$url = 'https://'.$this->bitrix24_url.'/crm/configs/import/lead.php';

			$options = array(
			    'http' => array(
			        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
			        'method'  => $this->method,
			        'content' => http_build_query($request)
			    )
			);
			$context  = stream_context_create($options);
			$result = file_get_contents($url, false, $context);
			return $result;
		}

		private function parse_json() {

			$this->log('Нотис', 'Парсим ЖСОН:'.$this->file.'');
			$row = 0;
			$array = array();

			if ($this->remote_source != 'google') {
				$this->log('Ошибка', 'Парс ЖСОН пока только для гугла');
				return $array;
			}

			if (($data = file_get_contents($this->file)) !== FALSE) {

				$data = json_decode($data, true);
				$data = $data['feed']['entry'];

				foreach ($data as $key => $vals) {
					$array[$row] = array();
					$i = 0;
					foreach ($vals as $keyrow => $val) {
						if (in_array($keyrow, array('id', 'updated', 'category', 'title', 'content', 'link'))) continue;
						$array[$row][$i] = $val['$t'];
						$i++;
					}
					$row++;
				}

			    $this->log('Нотис', 'Спарсили ЖСОН:'.$this->file.' Все строк:'.count($array));
			} else {
				$this->log('Ошибка', 'Все плохо с ЖСОН:'.$this->file.'');
			}
			return $array;
		}

		private function parse_csv() {
			$this->log('Нотис', 'Парсим ЦСВ:'.$this->file.'');
			$row = 0;
			$array = array();
			if (($handle = fopen($this->file, "r")) !== FALSE) {
			    while (($data = fgetcsv($handle, $this->csv_string_length, $this->csv_delemiter)) !== FALSE) {
			        $row++;
			        if ($row == 1 && $this->escape_first_string) continue;
			        $array[] = $data;
			    }
			    fclose($handle);
			    $this->log('Нотис', 'Спарсили ЦСВ:'.$this->file.' Все строк:'.count($array));
			} else {
				$this->log('Ошибка', 'Все плохо с ЦСВ:'.$this->file.'');
			}
			return $array;
		}

		private function parse_xls() {

			if (!class_exists('Spreadsheet_Excel_Reader')) {
				$this->log('Ошибка', 'Не подключена библиотека для работы с xls, xlsx.');
				return;
			}
			$row = 0;
			$array = array();

			ini_set('mbstring.internal_encoding', 0); // битрикс, какой же ты мудак.
			ini_set('mbstring.func_overload', 0);

			$data = new Spreadsheet_Excel_Reader();
			$data->setOutputEncoding('UTF-8');
			$data->setUTFEncoder('mb');
			$data->setRowColOffset(0);

			try {

				$data->read($this->file);

				foreach ($data->sheets[0]['cells'] as $i => $value) {
					if ($i == 0 && $this->escape_first_string) continue;
					$array[] = $value;
				}
					
				
				$this->log('Нотис', 'Спарсили '.$this->extension.':'.$this->file.' Все строк:'.count($array));	
			} catch (Exception $e) {
				$this->log('Ошибка', 'Все плохо с чтением файла .xls/.xlsx:'.$e->getMessage());
			}


			return $array;

		}

		private function parse_xlsx() {
			$this->parse_xls();
		}

		private function get_file() {
			if ($this->remote_source == 'google') {
				if (!copy($this->file, $this->file_folder.'google.json')) {
					$this->log('Ошибка', 'Не возможно выкачать файл:'.$this->file);
					return false;
				}
				$this->file = $this->file_folder.'google.json';
			}
			
			if (!$this->file) {
				$this->log('Ошибка', 'Не задан файл.');
				return false;
			}
			$content = is_readable($this->file);
			if (!$content) {
				$this->log('Ошибка', 'Файл '.$this->file.' не существует, либо он пустой.');
				return false;
			}
			if ($this->hash_exist()) return false;
			return true;
		}

		private function log($type, $data) {
			$string = date('d-m-Y H:i').' '.$type.': '.$data;
			file_put_contents($this->log_file, $string . "\n", FILE_APPEND);
		}

		private function get_hashes() {
			$data = file_get_contents($this->hash_file);
			if (!$data) {
				$this->log('Нотис', 'Файл хэшей пуст.');
				return array();
			}
			return json_decode($data, true);
		}

		private function hash_exist() {
			$hash = hash_file('md5', $this->file);

			if ($this->ignore_hash) {
				$this->log('Нотис', 'Хэши игнорируются.');
				$this->hashes[$this->file] = $hash;
				return false;
			}

			if (!isset($this->hashes[$this->file])) {
				$this->log('Нотис', 'Хэша для файла '.$this->file.' не существует.');
			} elseif ($this->hashes[$this->file] != $hash) {
				$this->log('Нотис', 'Хэш для файла '.$this->file.' не совпал - файл изменился.');
			} else {
				$this->log('Нотис', 'Хэш для файла '.$this->file.' совпал - файл не изменился с последнего раза, пропускаем его.');
				return true;
			}

			$this->log('Нотис', 'Записываем новый хэш '.$this->file.'.');
			$this->hashes[$this->file] = $hash;
			return false;
		}

		private function save_hashes() {
			$string = json_encode($this->hashes);
			file_put_contents($this->hash_file, $string);
		}

		private function delete_tmp() {
			//if (file_exists($this->hash_file)) unlink($this->hash_file);
			if (file_exists($this->log_file)) unlink($this->log_file);
		}

	}

 ?>