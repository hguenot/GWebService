&lt;?php

Yii::import('ext.GWebService.GSoapClient');


/**
<?php 

$schema = null;

foreach($wsdl->schemas as $ns => $s) {
	$schema = $s[0];
	break;
}

$this->writeDumpComment($debug, $wsdl); 
$types = array();
$typedefs = array();
$classes = array();

foreach ($wsdl->getOperations() as $operation => $def) {
	if (isset($def['input']) && isset($def['input']['parts'])) {
		foreach ($def['input']['parts'] as $n => $typedef) {
			list($ns, $t) = $this->split_type($typedef);
			$type = $wsdl->getTypeDef($t, $ns);
			if ($type === false) {
				$type = $schema->getPHPType($t, $ns);
			}
			if ($type === false || $type === null)
				$type = 'mixed';
			
			$typedefs[$typedef] = $type;
			$typedefs[$t] = $type;
			$classes[$typedef] = $type;
		}
	}
	
	if (isset($def['output']) && isset($def['output']['parts'])) {
		foreach ($def['output']['parts'] as $n => $typedef) {
			list($ns, $t) = $this->split_type($typedef);
			$type = $wsdl->getTypeDef($t, $ns);
			if ($type === false) {
				$type = $schema->getPHPType($t, $ns);
			}
			if ($type === false || $type === null)
				$type = 'mixed';
			
			$typedefs[$typedef] = $type;
			$typedefs[$t] = $type;
			$classes[$typedef] = $type;
		}
	}
}	

foreach ($typedefs as $ns => $type) {
	if (is_array($type)) {
		if ($type['phpType'] == 'array') {
			$types[$ns] = $type['arrayType'].'[]|array';
		} else {
			$types[$ns] = $type['name'].'|array';
		}
	} else if (is_string($type)) {
		$types[$ns] = $type;
	} else {
		$types[$ns] = 'mixed|unknown';
	}
}

if (count($classes) > 0) {
	echo " * This service use below complex types : \r\n";
}
foreach ($classes as $ns => $type) {
	if (is_array($type) && $type['phpType'] !== 'array') {
?>
 * <pre>class <?php echo $type['name'] ?> {
<?php 
		foreach ($type['elements'] as $name=>$def) {
			$type = $def['type'];
			if (isset($types[$def['type']]));
				$type = $types[$def['type']];
			$type = str_replace('|array', '', $type);
			echo " *     public $type \$$name;\r\n";
		}
?>
 * }</pre>
 *
<?php 
	}
}
?>
 */
class <?php echo $className; ?> extends GSoapClient {

	public function __construct() {
		$this-&gt;wsdlUrl = "<?php echo $wsdlUrl; ?>";
	}
<?php 

foreach ($wsdl->getOperations() as $operation => $def) :
	$name = $this->last_element(".", $operation);

	$inputs = array();
	if (isset($def['input']) && isset($def['input']['parts'])) {
		foreach ($def['input']['parts'] as $n => $type) {
			$inputs[$n] = $types[$type];
		}
	}
	
	$outputs = array();
	if (isset($def['output']) && isset($def['output']['parts'])) {
		foreach ($def['output']['parts'] as $n => $type) {
			$outputs[$n] = $types[$type];
		}
	}
?>

<?php $this->writeDumpComment($debug, array($name => $def)); ?>
    /**
<?php
    foreach ($inputs as $n => $type) {
		if(($pos=strpos($type,'[]'))!==false) // if it is an array
		{
			$type=substr($type,0,$pos);
	    	echo "     * @param {$type}[]|array \$$n Of type $type (or structure)\r\n";
		} else {
			echo "     * @param $type \$$n\r\n";
		}
    }
    foreach ($outputs as $n => $type) {
		$t = explode('|', $type, 2);
		if(($pos=strpos($type,'[]'))!==false) // if it is an array
		{
			$t[0]=substr($type,0,$pos);
			echo "     * @return array Array of {$t[0]}'s structure\r\n";
		} else {
			echo "     * @return array {$t[0]}'s structure\r\n";
		}
    }
?>     */
	public function <?php echo $name ?>(<?php 
$sep = "";
foreach ($inputs as $name => $type) {
	echo "$sep\$$name";
	$sep = ", ";
}
?>) {
		$params = array();
<?php 
		$sep = "";
		foreach ($inputs as $name => $type) {
			echo "\t\t\$params['$name'] = \$$name;\n";
			$sep = ", ";
		}
?>
		return $this-&gt;call('<?php echo $operation; ?>', $params);
	}

<?php 
endforeach;
?>
}

<?php
