<?php
	function parseArgument($arg) {

		$attr = array();
		$cond = array();
		$q = new Query();

		$regex_p = '/^.+?(?=\()/';
		$regex_c = '/\(([^)]+)\)/';
		$delimiter = '/[\s,]+/';

		preg_match($regex_p, $arg, $predicate);
		preg_match($regex_c, $arg, $conditions);
		$temp = preg_split($delimiter, $conditions[1]);

		$i=1;
		$key = "argumen_";
		foreach ($temp as $arg) {
			if(isVariable($arg)) {
				$attr[$key.$i] = $arg;
				$i++;
			}
			else {
				$cond[$key.$i] = $arg;
				$i++;
			}
		}

		$q->setPredicate($predicate[0]);
		$q->setAttributes($attr);
		$q->setConditions($cond);

		//echo($predicate[0]);
		//print_r($temp);
		return $q;
	}

	function parseRule($arg) {

		$attr = array();
		$cond = array();
		$q = new Query();

		$regex_p = '/^.+?(?=\()/';
		$regex_c = '/\(([^)]+)\)/';
		$delimiter = '/[\s,]+/';

		preg_match($regex_p, $arg, $predicate);
		preg_match($regex_c, $arg, $conditions);
		$temp = preg_split($delimiter, $conditions[1]);
		//print_r($temp);
		$i=1;
		$key = "argumen_";
		foreach ($temp as $arg) {
				$cond[$key.$i] = $arg;
				$i++;
		}

		$q->setPredicate($predicate[0]);
		$q->setConditions($cond);

		//echo($predicate[0]);
		//print_r($temp);
		return $q;
	}

	function parseFormula($arg, $substitution) {

		$delimiter = '/[ ]+/';
		$temp = preg_split($delimiter, $arg);

		for($i=0; $i<sizeof($temp); $i++) {
			if(isVariable($temp[$i])) {
				$temp[$i] = $substitution[$temp[$i]];
			}
		}
		// print_r($temp);	
	}

	function isVariable($arg) {
		return ctype_upper($arg);
	}

	function isComparator($arg) {
		if($arg == '=' OR $arg == '<>' OR $arg == '>=' OR $arg == '=<' OR $arg == '>' OR $arg == '<') {
			return true;
		}
		else {
			return false;
		}
	}

	function hasVariant($head) { //check if any rule has more than one statement 
		global $conn;

		//check how many rules that have similar head
		$stmt = $conn->prepare("SELECT COUNT(id_aturan) FROM idb i INNER JOIN predikat p ON i.id_predikat = p.id_predikat WHERE nama_predikat = '$head'");		
		$stmt->execute();
		$amount = $stmt->fetch();
		//echo $amount[0]."\n";
		if($amount[0] > 1) {
			return true;
		}
		else {
			return false;
		}
	}

	function numVariant($head) { //count how many variations exist for a rule 
		global $conn;

		//count how many rules that have similar head
		$stmt = $conn->prepare("SELECT COUNT(id_aturan) FROM idb i INNER JOIN predikat p ON i.id_predikat = p.id_predikat WHERE nama_predikat = '$head'");		
		$stmt->execute();
		$amount = $stmt->fetch();

		return $amount[0];
	}

	function isIDB($predicate) { //check whether the predicate is IDB or not
		global $conn;
		
		$stmt = $conn->prepare("SELECT `kelompok_predikat` FROM predikat WHERE nama_predikat = '$predicate'");
		$stmt->execute();
		$result = $stmt->fetch();

		if($result[0] == "IDB") {
			return true;
		}
		else {
			return false;
		}
	}

	function getReference($predicate) { //get reference table for a predicate
		global $conn;

		$ref = new Reference();
		$stmt = $conn->prepare("SELECT `id_ref`, `table_name`, `db_name` FROM `reference` WHERE id_ref = '$predicate'");
		$stmt->execute();

		$id_ref = "";
		while ($res = $stmt->fetch()) {
			$id_ref = $res['id_ref'];
			$ref->setDatabase($res['db_name']);
			$ref->setTablename($res['table_name']);
		}

		$attr = array();
		$stmt = $conn->prepare("SELECT `attr_name` FROM `ref_attribute` WHERE id_ref = '$id_ref' ORDER BY `order` ASC");
		$stmt->execute();

		$i=0;
		while ($res = $stmt->fetch()) {
			$attr[$i] = $res['attr_name'];
			$i++;
		}
		$ref->setAttributes($attr);

		// print_r($ref);
		return $ref;
	}

	function getIDB($rule_id) {
		global $conn;

		$idb = array();
		$stmt = $conn->prepare("SELECT `nama_predikat` FROM `body_idb` b INNER JOIN `predikat` p ON b.predikat = p.id_predikat WHERE b.id_aturan = $rule_id AND p.kelompok_predikat = 'IDB'");
		$stmt->execute();
		
		$i = 0;
		while($edb = $stmt->fetch()) {
			$idb[$i] = $edb['nama_predikat'];
			$i++;
		}

		return $idb;
	}

	function getIDBList($predicate) {
		global $conn;

		//get head part of idb
		$stmt = $conn->prepare("SELECT id_aturan FROM idb i INNER JOIN predikat p ON i.id_predikat = p.id_predikat  WHERE nama_predikat = '$predicate'");
		$stmt->execute();
		$rule = $stmt->fetch();
		$rule_id = $rule[0];

		$idb = getIDB($rule_id);
		$list = array();

		array_push($list, $predicate);
		while(!empty($idb)) {
			$elmt = array_pop($idb); //get last element from idb array
			array_push($list, $elmt);
			$stmt = $conn->prepare("SELECT id_aturan FROM idb i INNER JOIN predikat p ON i.id_predikat = p.id_predikat WHERE nama_predikat = '$elmt'");
			$stmt->execute();
			$id = $stmt->fetch();
			$head = $id[0];
			if(!empty($head)) {// check if array is empty
				$temp = getIDB($head); //get another idb predicate (if any)
				$idb = array_merge($idb, $temp);
			}
		}

		$reverse = array_reverse($list);
		return $reverse;
	}

	function getHead($predicate) {
		global $conn;

		//get head part of idb
		$stmt = $conn->prepare("SELECT id_predikat FROM predikat WHERE nama_predikat = '$predicate'");
		$stmt->execute();
		$id = $stmt->fetch();
		$head = $id[0];

		//get corresponding id for this head
		$stmt = $conn->prepare("SELECT id_aturan FROM idb WHERE id_predikat = $head");
		$stmt->execute();
		$rule_id = $stmt->fetch(); 
		$rule = $rule_id[0];

		//get head argument(s)
		$res = array();
		$stmt = $conn->prepare("SELECT id_rule, urutan, isi_argumen FROM `argumen_head` WHERE id_rule = $rule");
		$stmt->execute();
	  $i = 0;
		while($head_arg = $stmt->fetch()) {
			$rh = new RuleHead();
			$rh->setPredicate($predicate);
			$rh->setArgOrder($head_arg['urutan']);
			$rh->setContent($head_arg['isi_argumen']);

			$res[$i] = $rh;
			$i++;
		} 

		return $res;
	}

	function getRuleBody($predicate) {
		global $conn;

		//get head part of idb
		$stmt = $conn->prepare("SELECT id_predikat FROM predikat WHERE nama_predikat = '$predicate'");
		$stmt->execute();
		$id = $stmt->fetch();
		$head = $id[0];

		$stmt = $conn->prepare("SELECT id_aturan FROM idb WHERE id_predikat = $head");
		$stmt->execute();
		
		$res = array();
		if(!hasVariant($predicate)) { //only one rule has this head
			$rule_id = $stmt->fetch(); 
			$rule = $rule_id[0];
			$res = getBody($rule);
			// $test = getIDBList($predicate); echo "IDB List \n"; print_r($test);		
		}
		else { //some rules have similar head
			$i = 0;
			while ($rule_id = $stmt->fetch()) {
				$rules[$i] = $rule_id['id_aturan'];
				$i++;
			}

			$i = 0;
			foreach ($rules as $rule) {
				$res[$i] = getBody($rule);
				$i++;
			}
		}
		
		return $res; 
	}

	function getBody($rule) {
		global $conn;

		$res = array();
		$stmt = $conn->prepare("SELECT p.nama_predikat, is_negasi, a.urutan_body, urutan_argumen, isi_argumen FROM `body_idb` b, `argumen_body` a, `predikat` p WHERE p.id_predikat = b.predikat AND b.urutan_body = a.urutan_body AND a.id_aturan = $rule AND b.id_aturan = $rule");
		$stmt->execute();
		$i = 0;
		while($body = $stmt->fetch()) {
			$rb = new RuleBody();
			$rb->setPredicate($body['nama_predikat']);
			$rb->setNegasi($body['is_negasi']);
			$rb->setBodyOrder($body['urutan_body']);
			$rb->setArgOrder($body['urutan_argumen']);
			$rb->setContent($body['isi_argumen']);

			$res[$i] = $rb;
			$i++;
		}
		return $res;
	}

	function mergeBody($bodies, $cons) {
		global $conn;  //print_r($bodies)."\n";

		$predicate = "";
		$predicates = array();

		$i = 0; $j = 0;
		$obj = "";
		while ($i<sizeof($bodies)) {
			$p = $bodies[$i]->getPredicate();
			$q = "";
			$constant = "";

			if($i<sizeof($bodies)-1) {
				$q = $bodies[$i+1]->getPredicate();
			}

			if(!isComparator($p)) {
				$constant = $cons[$bodies[$i]->getArgOrder()-1];
			}
			else {
				$constant = $bodies[$i]->getContent();
			}

			//if(!isComparator($p)) {
				if(strcmp($obj, $p) != 0) {
					$obj = $p;
					$predicate = $predicate.$obj."(".$constant;
					if(strcmp($p, $q) != 0) {// check if there is only one argument
						$predicate = $predicate.")";
						$predicates[$j] = $predicate;
						$j++;
						$predicate = "";	
					}
				}
				else {
					//echo $p." ".$q."\n";
					$predicate = $predicate.",".$constant;
					if(strcmp($p, $q) != 0) {// check if there is no more argument
						$predicate = $predicate.")";
						$predicates[$j] = $predicate;
						$j++;
						$predicate = "";	
					}
				}
			$i++;
		}
	
		//print_r($predicates);
		return $predicates;
	}

	function sortBody($bodies) {
		for($i=1; $i<sizeof($bodies); $i++) {
			$key = $bodies[$i];
			$j = $i-1;
			while (($j>=0) && ((($key->getBodyOrder() < $bodies[$j]->getBodyOrder()) || (($key->getBodyOrder() <= $bodies[$j]->getBodyOrder()) && ($key->getArgOrder() < $bodies[$j]->getArgOrder()))))) {
				$bodies[$j+1] = $bodies[$j];
				$j--;
			}
			$params[$j+1] = $key;
			//print_r($bodies);
		}
		return $bodies;
	}

	function combineResult($truthval) {

		$bool = "False";
		for ($i=0; $i<sizeof($truthval)-1; $i++) {
			if ($truthval[$i] || $truthval[$i+1]) {
				$bool = "True";
			}
		}
		return $bool;
	}

	function substituteVar($bodies) {
		global $conn;

		$known = array(); //array of variable that is already substituted
		$substitution = array(); //array of constant which substitute associated variable
		$i = 0;
		foreach ($bodies as $body) {
			if(!in_array($body->getContent(), $known) && isVariable($body->getContent())) {
				$known[$i] = $body->getContent();
				$order = $body->getArgOrder();
				$predicate = $body->getPredicate();

				if(isIDB($body->getPredicate())) { //get reference for idb if any
					$stmt = $conn->prepare("SELECT `id_predikat` FROM predikat WHERE nama_predikat = '$predicate'");
					$stmt->execute();
					$res = $stmt->fetch();
					$id = $res[0];

					$stmt = $conn->prepare("SELECT `reference` FROM predicate_ref WHERE id_predikat = $id");
					$stmt->execute();
					$res = $stmt->fetch();
					if(!empty($res[0])) {
						$table = $res[0];
						$stmt = $conn->prepare("SELECT `attr_name` FROM `reference` b INNER JOIN `ref_attribute` a ON b.id_ref = a.id_ref WHERE b.table_name = '$table' AND a.order = $order");
						$stmt->execute();
						$result = $stmt->fetch();
						
						$substitution[$body->getContent()] = $predicate.".".$result[0]; //get substitution for variable(s)
					}
				}
				else {
					$stmt = $conn->prepare("SELECT `attr_name` FROM `reference` b INNER JOIN `ref_attribute` a ON b.id_ref = a.id_ref WHERE b.table_name = '$predicate' AND a.order = $order");
					$stmt->execute();
					$result = $stmt->fetch();
					
					$substitution[$body->getContent()] = $predicate.".".$result[0]; //get substitution for variable(s)
				}
				$i++;
			}
		}
		// print_r($substitution);
		return $substitution;
	}

	function assignConstant($predicate, $substitution, $cons) {
		
		$head = getHead($predicate);
		// print_r($head);
		for($i=0; $i<sizeof($cons); $i++) {
			if(!isVariable($cons[$i])) {
				$substitution[$head[$i]->getContent()] = $cons[$i];
			}
		}
		//echo "After assignment \n";
		//print_r($substitution);
		return $substitution;
	}

	function collectRules($predicate) {

		$idb = getIDBList($predicate); //get every idb which involve in corresponding rule
		// print_r($idb);
		$test = array();
		for ($i=0; $i<sizeof($idb); $i++) {
			$idx = sizeof($test);
			if(hasVariant($idb[$i])) {
				$temp = getRuleBody($idb[$i]);
				foreach ($temp as $body) {
				 	$test[$idx] = sortBody($body);
				 	$idx++;
				 } 
			}
			else { 
				$test[$idx] = sortBody(getRuleBody($idb[$i])); //rule construction
			}
		}
		// print_r($test);
		return $test;
	}

?>