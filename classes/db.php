<?php 
error_reporting(E_ERROR | E_PARSE);

class Db {
	public $db;
	public $sql;
	public $where_flag=false;
	public $fieldsRegex;
	public $fieldCleanRegex;
	public function __construct($db){
		$this->db=$db;
		$this->fieldsRegex="/={0,1}[\s]?:([^\s)%,]+)/";
		$this->fieldCleanRegex="/={0,1}[\s]*:{0,1}/";
	}
	public function get_results($sql, $params = array(),$query_display=false) {
		$this->where_flag=false;
		$this->sql=$sql;
		$query = $this->db->prepare($sql);
		preg_match_all($this->fieldsRegex,$sql , $out, PREG_PATTERN_ORDER);
		$i=0;
		foreach ($out[0] as $p) {
			$replacing_element=preg_replace($this->fieldCleanRegex,"",$p);
			$query->bindParam($replacing_element, $params[$i]);  
			$i++;
		}
		$query->execute();
		if($query->errorCode() == '00000')
		{
			if($query_display){
				
				$i=0;
				foreach ($out[0] as $p) {
					$sql=str_replace("$p",$params[$i],$sql);
					$i++;
				}
				$sql=str_replace('\\','',$sql);
				highlight_string("<?php\n\$query =\n" . var_export($sql, true) . ";\n?>");
				debug_print_backtrace();

			}
			
			// success
			$data = $query->fetchAll(PDO::FETCH_ASSOC);
		}
		else
		{
			if(DEBUG_MODE){
				$data=$query->errorInfo()[2];
				$i=0;
				foreach ($out[0] as $p) {
					$sql=str_replace($p,$params[$i],$sql);
					$i++;
				}
				$sql=str_replace('\\','',$sql);
				$data.=" query:".$sql;
				highlight_string("<?php\n\$error =\n" . var_export($query->errorInfo()[2], true) . ";\n?><br>");
				highlight_string("<?php\n\$query =\n" . var_export($sql, true) . ";\n?>");
				debug_print_backtrace();
				exit;
			}
			else{
				$data=l("Database error");;
			}
		}
		
		return $data;
	}
	public function get_row($sql, $params = array(),$query_display=false) {
		$this->where_flag=false;
		$this->sql=$sql;
		$query = $this->db->prepare($sql);
		// $this->where($params);
		preg_match_all($this->fieldsRegex,$sql , $out, PREG_PATTERN_ORDER);
		$i=0;
		$duplicates=array();
		foreach ($out[0] as $p) {
			$replacing_element=preg_replace($this->fieldCleanRegex,"",$p);
			if(!in_array($replacing_element,$duplicates)){
				array_push($duplicates,$replacing_element);
				$query->bindParam($replacing_element, $params[$i]);  
				$i++;
			}
		}
		$query->execute();
		if($query->errorCode() == '00000')
		{
			if($query_display){
				highlight_string("<?php\n\$query =\n" . var_export($sql, true) . ";\n?>");
				debug_print_backtrace();

			}
			// success
		$data = $query->fetch(PDO::FETCH_ASSOC);
		}
		else
		{
			if(DEBUG_MODE){
				$data=$query->errorInfo()[2];
				$i=0;
				foreach ($out[0] as $p) {
					$sql=str_replace($p,$params[$i],$sql);
					$i++;
				}
				$sql=str_replace('\\','',$sql);
				$data.=" query:".$sql;
				highlight_string("<?php\n\$error =\n" . var_export($query->errorInfo()[2], true) . ";\n?><br>");
				debug_print_backtrace();
				highlight_string("<?php\n\$query =\n" . var_export($sql, true) . ";\n?>");
				exit;
			}
			else{
				$data=l("Database error");;
			}
		}
		return $data;
	}
	public function lastInsertId(){
		return $this->db->lastInsertId();
	}
	public function query($sql, $params = array(),$query_display=false) {
		$this->where_flag=false;
		$this->sql=$sql;
		$query = $this->db->prepare($sql);
		// $this->where($params);
		preg_match_all($this->fieldsRegex,$sql , $out, PREG_PATTERN_ORDER);
		$i=0;
		foreach ($out[0] as $p) {
			$replacing_element=preg_replace($this->fieldCleanRegex,"",$p);
			$query->bindParam($replacing_element, $params[$i]);  
			$i++;
		}
		$query->execute();
		if($query->errorCode() == '00000')
		{
			if($query_display){
				
				$i=0;
				foreach ($out[0] as $p) {
					$sql=str_replace("$p",$params[$i],$sql);
					$i++;
				}
				$sql=str_replace('\\','',$sql);
				debug_print_backtrace();
				highlight_string("<?php\n\$query =\n" . var_export($sql, true) . ";\n?>");
			}
			// success
		$data = true;
		}
		else
		{
			if(DEBUG_MODE){
				$data=$query->errorInfo();
				// $data.=" query:".$sql;
				highlight_string("<?php\n\$errorx =\n" . var_export($data, true) . ";\n?>");
				debug_print_backtrace();
				exit;
			}
			else{
				$data=l("Database error");;
			}
		}
		return $data;
	}

	public function get_var($sql, $params = array(),$query_display=false) {
		$this->where_flag=false;
		$this->sql=$sql;
		$query = $this->db->prepare($sql);
		// $this->where($params);
		preg_match_all($this->fieldsRegex,$sql , $out, PREG_PATTERN_ORDER);
		$i=0;
		
		foreach ($out[0] as $p) {
			$replacing_element=preg_replace($this->fieldCleanRegex,"",$p);
			$query->bindParam($replacing_element, $params[$i]);  
			$i++;
		}
		$query->execute();
		if($query->errorCode() == '00000')
		{
			if($query_display){
				highlight_string("<?php\n\$query =\n" . var_export($sql, true) . ";\n?>");
				debug_print_backtrace();
			}
			// success
		$data = $query->fetch();
		}
		else
		{
			if(DEBUG_MODE){
				$data=$query->errorInfo()[2];
				$data.=" query:".$sql;
				highlight_string("<?php\n\$error =\n" . var_export($data, true) . ";\n?>");
				debug_print_backtrace();
				exit;
			}
			else{
				$data=l("Database error");;
			}
		}
		return $data[0];
	}
	




}

?>
