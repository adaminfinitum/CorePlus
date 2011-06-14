<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ContentModel
 *
 * @author powellc
 */
class ContentModel extends Model{
	
	public static $Schema = array(
		'id' => array(
			'type' => Model::ATT_TYPE_ID,
			'required' => true,
			'null' => false,
		),
		'title' => array(
			'type' => Model::ATT_TYPE_STRING,
			'maxlength' => 128,
			'null' => false,
		),
		'description' => array(
			'type' => Model::ATT_TYPE_TEXT,
			'null' => false,
		),
		'keywords' => array(
			'type' => Model::ATT_TYPE_TEXT,
			'null' => false,
		),
		'access' => array(
			'type' => Model::ATT_TYPE_STRING,
			'maxlength' => 512,
			'default' => '*',
			'null' => false,
		),
	);
	
	public static $Indexes = array(
		'primary' => array('id'),
	);
	
    public function __construct($key = null) {
		/*$this->_linked = array(
			'Page' => array(
				'link' => Model::LINK_HASONE,
				'on' => 'baseurl',
			),
		);*/
		
		parent::__construct($key);
	}
	
	public function get($k) {
		$k = strtolower($k);
		switch($k){
			case 'baseurl':
				return '/Content/View/' . $this->_data['id'];
				break;
			default:
				return parent::get($k);
		}
	}
}
?>
