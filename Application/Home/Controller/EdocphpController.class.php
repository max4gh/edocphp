<?php

/**
 * EdocPHP  
 * 
 * php代码调试工具
 * 
 * @author Max
 * @date   2016-01-12
 */
namespace Home\Controller;

use Think\Controller;

/**
 * 
 * @author Max
 *
 */
class EdocphpController extends Controller {
	
	/**
	 * 查看类列表
	 * 
	 * @param string $module
	 *        	分层
	 */
	public function index($module = "Model") {
		if (! APP_DEBUG) {
			die ( "请打开代码调试模式" );
		}
		$serviceList = $this->getClassList ( $module );
		$this->assign ( 'data', json_encode ( $serviceList ) );
		$this->assign ( 'module', $module );
		$this->display ();
	}
	
	/**
	 * 返回指定分层的类列表
	 * 
	 * @param string $type 
	 * @param string $dir
	 * @param string $subGroup       	
	 */
	public function getClassList($type = "Service", $dir = '', $subGroup = '') {
		if ($dir == '') {
			$dir = dirname ( dirname ( __FILE__ ) ) . DIRECTORY_SEPARATOR."$type";
		}
		
		$d = dir ( $dir );
		$files = [ ];
		while ( false !== ($file = $d->read ()) ) {
			if ($file != '.' && $file != '..') {
				$temp = [ ];
				if (is_file ( $dir . DIRECTORY_SEPARATOR . $file ) && strpos ( $file, '.php' )) {
					list ( $className ) = explode ( '.', $file );
					$temp ['name'] = $className;
					if ($subGroup == '') {
						$temp ['class'] = base64_encode ( MODULE_NAME . "\\" . $type . "\\" . $className );
					} else {
						$temp ['class'] = base64_encode ( MODULE_NAME . "\\" . $type . "\\" . $subGroup . "\\" . $className );
					}
					
					$temp ['filePath'] = $dir . DIRECTORY_SEPARATOR . $file;
					$temp ['children'] = $this->methodList ( $temp ['class'] );
				} else if (is_dir ( $dir . DIRECTORY_SEPARATOR . $file )) {
					$temp ['name'] = $file;
					if ($subGroup == '') {
						$subGroup = $file;
					} else {
						$subGroup = $subGroup . DIRECTORY_SEPARATOR . $file;
					}
					$temp ['children'] = $this->getClassList ( $type, $dir . DIRECTORY_SEPARATOR . $file, $subGroup );
					$subGroup = ''; // 清除条件
				}
				$files [] = $temp;
			}
		}
		$d->close ();
		return $files;
	}
	
	/**
	 * 反射出类的方法列表
	 * 
	 * @param string $class 目标反射类base64_encode  	
	 */
	public function methodList($class) {
		$class = base64_decode ( $class );
		try {
			$reflector = new \ReflectionClass ( $class );
			$methods = $reflector->getMethods ( );
			$data = [ ];
			foreach ( $methods as $v ) {
				$method = [ ];
				$method ['name'] = $v->name;
				$declaringClass = $v->getDeclaringClass();
				//只需要返回类定义的方法，不返回继承的方法
				if($declaringClass->name != $class) continue;
				$method ['class'] = base64_encode ( $v->class );
				$data [] = $method;
			}
		} catch ( \ReflectionException $e ) {
			dump ( $e->getMessage () );
		}
		return $data;
	}
	
	/**
	 * 反射方法详细及方法调用
	 * 
	 * @param string $class base64_encode String       	
	 * @param string $name        	
	 */
	public function debug($class, $name) {
		try {
			// echo base64_decode($class);return;
			$class = base64_decode ( $class );
			$file = dirname(dirname ( dirname ( __FILE__ ) )).DIRECTORY_SEPARATOR.$class.".class.php";
            $file = str_replace('\\',DIRECTORY_SEPARATOR,$file);
            $content = file($file );
			$method = new \ReflectionMethod ( $class, $name );
			$data = [ ];
			$data ['startLine'] = $method->getStartLine ();
			$data ['endLine'] = $method->getEndLine ();
			$data ['comment'] = nl2br ( $method->getDocComment () );
			
			$data ['parameters'] = [];
			$data ['className'] = $class;
			$data ['method'] = $name;
			$code = "<?php \n";
			$code .= implode(array_slice($content, $data['startLine']-1,$data['endLine']-$data['startLine']+1),""); 
			$code .= "?> \n";
			$data ['code'] = highlight_string($code,true);
			$parameters = $method->getParameters ();
			if(count($parameters) > 0){  //若方法自带参数，适用于model
				foreach ($parameters as $v){
					$p = ['name'=> $v->getName() ];
					$lines = explode("\n", $method->getDocComment ());
					if( count($lines)>0 ){
						foreach ($lines as $line){
							if(strpos($line,'@param')){
								$tmpLine = substr($line, strpos($line,'@param')+7);
								list($type, $name)= explode(' ', $tmpLine);
								if(trim($name) == '$'.$p['name']){
									$p['type'] = $type; 
									$p['desc'] = substr($line,strpos($line, $name)+strlen($name));
									break;
								}
							}
						}
					}
					if( !isset($p['type']) ) $p['type'] = 'unknow';
					$data['parameters'][] = $p;
				}
			}else{ //方法不带参数，适用于Controller
				$data['parameters'] = [];
				$lines = explode("\n", $method->getDocComment ());
				if( count($lines)>0 ){
					foreach ($lines as $line){
						if(strpos($line,'@param')){
							$tmpLine = substr($line, strpos($line,'@param')+7);
							list($p['type'],$p['name'], $p['desc'])= explode(' ', $tmpLine);
							$p['name'] = substr($p['name'], 1);
							$data['parameters'][] = $p;
						}
					}
				}				
			}
			
			if (I ( 'debug' )) {
				ob_start();
				if (I ( 'parameters' )) {
					$res = $method->invokeArgs ( new $class (), array_values ( I ( 'parameters' ) ) );
				} else {
					$res = $method->invoke ( new $class () );
				}
				$bf = ob_get_contents();
				ob_end_clean();
				
				$this->ajaxReturn (['bf'=>$bf, 'data'=>var_export ( $res, true )],'json');
			}
			
		} catch ( \Exception $e ) {
			echo $e->getMessage ();
		}
		$this->ajaxReturn ( $data );
	}
	
	/**
	 * 反射类描述
	 * @param string $class 类名，base64_encode
	 */
	public function getClassInfo($class){
		try {
			$reflector = new \ReflectionClass ( base64_decode ( $class ) );
			$document = nl2br($reflector->getDocComment ( ));
			$methods = $this->methodList($class);
			$file = dirname(dirname ( dirname ( __FILE__ ) )).DIRECTORY_SEPARATOR.base64_decode ( $class ).".class.php";
			$file = str_replace('\\',DIRECTORY_SEPARATOR,$file);
			$content = file($file );
			$data = ['comment'=>$document, 'methods'=>$methods,'name'=>base64_decode ( $class ), 'code'=>highlight_file($file,true) ];
		} catch ( \ReflectionException $e ) {
			dump ( $e->getMessage () );
		}
		return $this->ajaxReturn($data);
	}
	
	
	
	/**
	 * @param int $a 参数b的描述
	 * @param string $b 参数a的描述
	 */
	public function test(){
		echo 'test';
		return ;
	}
}
?>