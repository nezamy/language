<?php
namespace System;
use System\DF;
use System\FileSystem;
class Language
{
	private $path;
	private $mapPath;
	private $config = false;
	private $setting = false;
	private $default = 'default';
	private $currentModule = 'core';
	private $currentFile;
	private $currentLang;
	private $defaultLang;
	private $copyFrom;
	private $store = [];
	private static $instance;

	public function __construct()
	{
		$this->setConfig();
	}

	public static function instance()
	{
		if (null === static::$instance) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	public function setConfig()
	{
		if (!$this->config) {
			$this->setting 		= (new DF)->get('language.setting');
			$this->config   	= app()->config;
			$this->mapPath  	= 'language/map.php';
			$this->copyFrom 	= $this->setting->get('create_copy_from');
			$this->path($this->config->get('app.lang'));
			$this->switchTo('home')->setFile($this->default);
		}
	}

	private function path($path=null)
	{
		if($path) $this->path = $path;

		if(!isset($this->store[$this->currentModule]))	$this->store[$this->currentModule] = [];

		return str_replace('%module%', $this->currentModule, $this->path);
	}

	public function setModule($module){
		$this->currentModule = $module;
		return $this;
	}

	public function switchTo($name)
	{
		switch ($name) {
			case 'admin':
				$this->defaultLang  	= $this->setting->get('default_lang_admin', 'ar');
				$this->currentLang  	= session()->get($this->setting->get('lang_admin_session'), $this->defaultLang);
				session($this->setting->get('lang_admin_session'), $this->currentLang); break;

			default:
				$this->defaultLang  	= $this->setting->get('default_lang_home', 'ar');
				$this->currentLang  	= session()->get($this->setting->get('lang_home_session'), $this->defaultLang);
				session($this->setting->get('lang_home_session'), $this->currentLang); break;
		}
		return $this;
	}

	public function setFile($f) {
		$this->currentFile = $f;
		return $this;
	}

	public function getFileList($code)
	{
		$list = array_diff(scandir($this->path().DS.$code), array('.','..'));
		$files = [];

		foreach ($list as $value) {
			if (is_file($this->path().DS.$code.DS.$value)) {
				$files[] = substr($value, 0, -4);
			}
		}
		return $files;
	}

	//get newer keys from other lang
	//$code = current lang code
	//$file = file you want to refresh
	//$from = lang have new keywords
	public function refreshFileKeywords($code, $file , $from)
	{

	}

	public function getFileWordsList($code, $file)
	{
		$file = $this->path().DS.$code.DS.$file.'.php';
		if (file_exists($file)) {
			return include $file;
		} return false;
	}
	//$where i.e ['code' => 'ar']
	public static function getAll($where=[])
	{
		return (new DF)->where($where)->get('language.map');
	}

	public static function getCountries($where=[]){
		return (new DF)->where($where)->get('language.countries-with-language');
	}

	public static function getLanguages($where=[]){
		return (new DF)->where($where)->get('language.languages');
	}

	public function get($key, $file=null)
	{
		if ($file !== null) $this->setFile($file);

		if ( array_key_exists($this->currentFile, $this->store[$this->currentModule]))
		{
			$store = $this->store[$this->currentModule][$this->currentFile];

			if (isset($store[$key])) {
				return $store[$key];
			}

			else {
				foreach ($this->store[$this->currentModule] as $v) {
					if (isset($v[$key]))
						return $v[$key];
				}
			}

			return $key;
		}

		else {

			$fs = (new FileSystem)->get($this->path().DS.$this->currentLang.DS.$this->currentFile.'.php');

			if ($fs->isFile()) {
				$this->store[$this->currentModule][$this->currentFile] = include $fs;
				return isset($this->store[$this->currentModule][$this->currentFile][$key]) ? $this->store[$this->currentModule][$this->currentFile][$key] : $key;
			} else {
				throw new \Exception("The Language File Not Found ($fs)");
			}
		}
	}

	// $langAndWords = [
	// 	'ar'=> [
	// 		'key' => 'value'
	// 	],
	// 	'en'=> [
	// 		'key' => 'value'
	// 	]
	// ];
	//if file exsists add new keys merge
	//if file not exsists add new file and set value
	public function set(array $langAndWords, $file=null, $delete=false)
	{
		$fileName = $this->default;
		if ($file !== null) $fileName = $file;

		foreach ($langAndWords as $k => $v)
		{
			$fs		= (new FileSystem)->makeFile($this->path().DS.$k.DS.$fileName.'.php');

			if ($fs->isFile() && $fs->getSize()) {
				$inc 	= include $fs;
			} else {
				$inc = [];
			}

			if ($delete) {
				foreach ($v as $key) {
					unset($inc[$key]);
				}

				$data = $inc;
			} else {
				$data 	= array_merge($inc, (array)$v);
			}

			$fs->openFile('w')->fwrite('<?php return ' . var_export($data, true) . ';');
		}
	}

	// $langAndWords = [
	// 	'ar'=> ['ke1','ke2'],
	// 	'en'=> ['ke1','ke2'],
	// ];
	public function remove(array $langAndWords, $file=null)
	{
		$this->set($langAndWords, $file, true);
	}

	public function create($code, array $setting)
	{
		(new DF)->save('language.map', [$code => $setting]);

		if (file_exists($this->path().DS.$this->copyFrom)) {
			// dpre([$this->path().DS.$code, $this->path().DS.$this->copyFrom]);
			return (new FileSystem)->copyDir($this->path().DS.$this->copyFrom, $this->path().DS.$code);
		} else {
			return (new FileSystem)->makeFile($this->path().DS.$code.DS.$this->default.'.php')
							->openFile('w')->fwrite('<?php return ' . var_export([], true) . ';');
		}
	}

	public function UpdateMap($data) {
		return (new DF)->save('language.map', $data);
	}

	public function delete($code)
	{
		if ( $code == $this->setting->get('default_lang_home') ||
		    $code == $this->setting->get('default_lang_admin') ) {
			return false;
		} else {
			if ( (new DF)->delete('language.map', (array)$code) ) {
				return (new FileSystem)->deleteDir($this->path().DS.$code);
			} return false;
		}
	}
}
