<?php
session_start();
require_once 'dbconfig.php';

class crud {

	private $db;
	private $dbhost=DBHOST;
	private $dbuser=DBUSER;
	private $dbpass=DBPWD;
	private $dbname=DBNAME;


	function __construct() {

		try {

			$this->db=new PDO('mysql:host='.$this->dbhost.';dbname='.$this->dbname.';charset=utf8',$this->dbuser,$this->dbpass);

		// echo "Bağlantı Başarılı";

		} catch (Exception $e) {

			die("Bağlantı Başarısız:".$e->getMessage());
		}

	}	

	public function addValue($argse) {

		$values=implode(',',array_map(function ($item){
			return $item.'=?';
		},array_keys($argse)));

		return $values;
	}


	public function insert($table,$values,$options=[]) {

		try {

			if (!empty($_FILES[$options['file_name']]['name'])) {
				
				$name_y=$this->imageUpload(
					$_FILES[$options['file_name']]['name'],
					$_FILES[$options['file_name']]['size'],
					$_FILES[$options['file_name']]['tmp_name'],
					$options['dir']
				);

				// print_r($name_y);
				// exit;

				$values+=[$options['file_name'] => $name_y];
			}



			if (isset($options['pass'])) {
				$values[$options['pass']]=md5($values[$options['pass']]);
			}
			// echo "<pre>";
			// print_r($values);
			// exit;


			unset($values[$options['form_name']]);
			

			$stmt=$this->db->prepare("INSERT INTO $table SET {$this->addValue($values)}");
			$stmt->execute(array_values($values));

			return ['status' => TRUE];
			
		} catch (Exception $e) {
			
			return ['status' => FALSE, 'error' => $e->getMessage()];
		}

	}


	public function update($table,$values,$options=[]) { 

		try {

			if (!empty($_FILES[$options['file_name']]['name'])) {

				
				$name_y=$this->imageUpload(
					$_FILES[$options['file_name']]['name'],
					$_FILES[$options['file_name']]['size'],
					$_FILES[$options['file_name']]['tmp_name'],
					$options['dir'],
					$values[$options['file_delete']]
				);

				// print_r($name_y);
				// exit;

				$values+=[$options['file_name'] => $name_y];
				
			}

			//Eski Resim Dosyasının Değerini Temizleme...
			unset($values[$options['file_delete']]);


			if (isset($options['pass'])) {
				$values[$options['pass']]=md5($values[$options['pass']]);
			}
			
			$columns_id=$values[$options['columns']];
			unset($values[$options['form_name']]);
			unset($values[$options['columns']]);
			$valuesExecute=$values;
			$valuesExecute+=[$options['columns'] => $columns_id];

			

			// echo "<pre>";
			// print_r($values);
			// print_r($valuesExecute);
			// exit;
			

			$stmt=$this->db->prepare("UPDATE $table SET {$this->addValue($values)} WHERE {$options['columns']}=?");
			$stmt->execute(array_values($valuesExecute));

			return ['status' => TRUE];
			
		} catch (Exception $e) {
			
			return ['status' => FALSE, 'error' => $e->getMessage()];
		}

	}



	public function delete ($table,$columns,$values,$fileName=null) {


		try {

			if (!empty($fileName)) {
				unlink("dimg/$table/".$fileName);
			}

			$stmt=$this->db->prepare("DELETE FROM $table WHERE $columns=?");
			$stmt->execute([htmlspecialchars($values)]);

			return ['status' => TRUE]; 
			
		} catch (Exception $e) {
			
			return ['status' => FALSE, 'error' => $e->getMessage()];
		}

	}


	public function imageUpload($name,$size,$tmp_name,$dir,$file_delete=null) {

		try {

			$izinli_uzantilar=[
				'jpg',
				'jpge',
				'png',
				'ico'
			];

			$ext=strtolower(substr($name, strpos($name, '.')+1));

			if (in_array($ext, $izinli_uzantilar)===false) {
				throw new Exception('Bu dosya türü kabul edilmemektedir...');
			}

			if ($size>1048576) {
				throw new Exception('Dosya boyutu çok büyük...');

			}

			$name_y=uniqid().".".$ext;

			if (!@move_uploaded_file($tmp_name, "dimg/$dir/$name_y")) {
				throw new Exception('Dosya yükleme hatası...');
			}

			if (!empty($file_delete)) {
				unlink("dimg/$dir/$file_delete");

				if (strstr($dir, "admin")) {
					$_SESSION["admins"]["admins_file"]=$name_y;
				}
				
			}
			
			return $name_y;



		} catch (Exception $e) {

			return ['status' => FALSE, 'error' => $e->getMessage()];
		}
	}

	public function adminInsert($admins_namesurname,$admins_username,$admins_pass,$admins_status) {

		try {

			$stmt=$this->db->prepare("INSERT INTO admins SET admins_namesurname=?,admins_username=?,admins_pass=?,admins_status=?");
			$stmt->execute([$admins_namesurname,$admins_username,md5($admins_pass),$admins_status]);

			return ['status' => TRUE];
			
		} catch (Exception $e) {
			
			return ['status' => FALSE, 'error' => $e->getMessage()];
		}

	}


	public function adminsLogin($admins_username,$admins_pass,$remember_me) {
		
		try {

			$stmt=$this->db->prepare("SELECT * FROM admins WHERE admins_username=? AND admins_pass=?");
			

			if (isset($_COOKIE['adminsLogin'])) {
				$stmt->execute([$admins_username,md5(openssl_decrypt($admins_pass, "AES-128-ECB", "admins_coz"))]);
			} else {
				$stmt->execute([$admins_username,md5($admins_pass)]);
			}



			if ($stmt->rowCount()==1) {

				$row=$stmt->fetch(PDO::FETCH_ASSOC);

				if ($row['admins_status']==0) {
					return ['status' => FALSE];
					exit;
				}

				$_SESSION["admins"]=[
					"admins_username" => $admins_username,
					"admins_namesurname" => $row['admins_namesurname'],
					"admins_file" => $row['admins_file'],
					"admins_id" => $row['admins_id']
				];

				if (!empty($remember_me) AND empty($_COOKIE['adminsLogin'])) {
					
					$admins=
					[
						"admins_username" => $admins_username,
						"admins_pass" => openssl_encrypt($admins_pass, "AES-128-ECB", "admins_coz")
					];

					setcookie("adminsLogin",json_encode($admins),strtotime("+30 day"),"/");

				} else if (empty($remember_me)) {

					setcookie("adminsLogin",json_encode($admins),strtotime("-30 day"),"/");
				}

				return ['status' => TRUE];


			} else {

				return ['status' => FALSE ];

			}


		} catch (Exception $e) {

			return ['status' => FALSE, 'error' => $e->getMessage()];

		}


	}

	public function read($table,$options=[]) {

		
		try {

			if (isset($options['columns_name']) && empty($options['limit'])) {

				$stmt=$this->db->prepare("SELECT * FROM $table order by {$options['columns_name']} {$options['columns_sort']}");
			
			} else if (isset($options['columns_name']) && isset($options['limit'])) {


				$stmt=$this->db->prepare("SELECT * FROM $table order by {$options['columns_name']} {$options['columns_sort']} limit {$options['limit']}");
			} else {

				$stmt=$this->db->prepare("SELECT * FROM $table");

			}

			
			$stmt->execute();

			return $stmt;
			
		} catch (Exception $e) {
			
			echo $e->getMessage();
			return false;
		}
	}


	public function wread($table,$columns,$values,$options=[]) {

		
		try {

			$stmt=$this->db->prepare("SELECT * FROM $table WHERE $columns=?");
			$stmt->execute([htmlspecialchars($values)]);

			return $stmt;
			
		} catch (Exception $e) {
			
			return ['status' => FALSE, 'error' => $e->getMessage()];
		}
	}




	public function qSql($sql,$options=[]) {

		try {

			$stmt=$this->db->prepare($sql);
			$stmt->execute();
			return $stmt;

		} catch (Exception $e) {

			return ['status' => FALSE, 'error' => $e->getMessage()];

		}
	}


	public function orderUpdate($table,$values,$columns,$orderId) { 


		try {

			foreach ($values as $key => $value) { 

				$stmt = $this->db->prepare("UPDATE $table SET $columns=? WHERE $orderId=?");
				$stmt->execute([$key,$value]);   

			}

			return ['status' => TRUE];

		} catch(PDOException $e) {    
			echo $e->getMessage();
			return ['status' => FALSE,'error'=> $e->getMessage()];

		}
	}

}

?>
