<?php

/**
 * 测试模型，仅为演示edocphp用
 * 
 * @author Max
 *
 */
 
namespace Home\Model;

class TestModel {
	/**
	 * 以下是参数的说明 
	 * 
	 * @param string $a a说明
	 * @param int $b b说明
	 */
	public function test( $a, $b ){
		echo $a;   //方法中输出       ------会显示在调试结果的"缓冲区输出"
		return $b; //方法的返回值   ------会显示在调试结果的"返回结果"
	}
}