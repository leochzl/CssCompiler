<?php

/**
 * CssPacker
 * @author : Léo CHUZEL
 *
 */
class CssPacker
{
	/*
	 * ATTRIBUTES
	 */
	
	// privates
	private $_cache;
	private $_cssFiles;
	private $_lessFiles;
	private $_hash;
	private $_blacklistDir;
	private $_blacklistFiles;
	private $_generation_dir;
	
	/* 
	 * PUBLIC FUNCTIONS
	 */
	
	// constructor
	public function __construct()
	{
		$this->_cache = true;
		$this->_hash = '';
		$this->_blacklistDir = array();
		$this->_blacklistFiles = array();
		$this->_cssFiles = $this->_lessFiles = array(
			'folders' => array(),
			'files' => array(),
		);
	}
	
	public function setBlacklistFiles($P_blacklist)
	{
		$blacklist = (!is_array($P_blacklist)) ? array($P_blacklist) : $P_blacklist;
		$this->_blacklistFiles = $blacklist;
	}
	public function setBlacklistDir($P_blacklist)
	{
		$blacklist = (!is_array($P_blacklist)) ? array($P_blacklist) : $P_blacklist;
		$this->_blacklistDir = $blacklist;
	}
	
	public function setCache($P_bool)
	{
		$this->_cache = $P_bool;
		
		// if cache is enabled
		if($this->_cache)
		{
			// if the generated file exists
			if(file_exists('css-packer.css'))
			{
				// Expires
				$expires = 60*60*24*365;

				// Header
				header('Content-type: text/css; charset=UTF-8');
				header("Pragma: public");
				header("Cache-Control: maxage=".$expires);
				header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$expires) . ' GMT');
				
				readfile('css-packer.css');
				die();
			}
		}
	}
	
	public function display()
	{
		// recuperer tous les fichiers du ou des dossier(s)
		$this->_generateFiles();
		
		// Header
		header('Content-type: text/css; charset=UTF-8');
		
		// afficher la source
		return $this->_getCss();
	}
	
	/*
	 * PRIVATE FUNCTIONS
	 */
	
	private function _getExtention($P_file)
	{
		$ext_search = explode('.',$P_file);
		return strtolower($ext_search[count($ext_search)-1]);
	}
	
	// scanner tous les fichiers
	private function _scanDir($P_dir='..', $P_isFolder=false)
	{
		$arrayAttribute = ($P_isFolder) ? 'folders' : 'files';
		
		$directory = opendir($P_dir) or die('Erreur');
		while($entry = @readdir($directory))
		{
			if(is_dir($P_dir.'/'.$entry) && !in_array($entry,$this->_blacklistDir) && $entry != '.' && $entry != '..')
			{
				$this->_scanDir($P_dir.'/'.$entry, true);
			}
			elseif($entry != '.' && $entry != '..')
			{
				$extention = $this->_getExtention($entry);
				if(($extention == 'css' || $extention == 'less') && !in_array($entry,$this->_blacklistDir) && !in_array($entry,$this->_blacklistFiles) && !in_array($P_dir.'/'.$entry,$this->_cssFiles)) 
				{
					if($extention == 'less')
					{
						array_push($this->_lessFiles[$arrayAttribute],$P_dir.'/'.$entry);
					}
					else
					{
						array_push($this->_cssFiles[$arrayAttribute],$P_dir.'/'.$entry);
					}
				}
			}
		}
		closedir($directory);
	}
	
	private function _generateFiles()
	{
		// close_compiler to blacklistDir
		array_push($this->_blacklistDir, basename(dirname(__FILE__)));
		array_push($this->_blacklistFiles, "css-packer.css");
		$this->_scanDir();
	}
	
	private function _getCurrentHash()
	{
		return @file_get_contents('css-packer.hash');
	}
	
	private function _getFileHash()
	{
		$this->_hash = '';
		foreach($this->_cssFiles as $file)
		{
			$this->_hash .= filemtime($file);
		}
		foreach($this->_lessFiles as $file)
		{
			$this->_hash .= filemtime($file);
		}
		$this->_hash = md5($this->_hash);
		return $this->_hash;
	}
	
	private function _generateHash()
	{
		file_put_contents('css-packer.hash',$this->_hash);
	}
	
	private function _isCache()
	{
		$bool = false;
		if($this->_cache)
		{
			if($this->_getCurrentHash() == $this->_getFileHash())
			{
				$bool = true;
			}
		}
		if(!$bool)
		{
			$this->_generateHash();
		}
		return $bool;
	}
	
	private function _generateSource()
	{
		$output = $this->_getOptimizedSource();
		file_put_contents('css-packer.css',$output);
		return $output;
	}
	
	private function _generateExactCssUrl($content, $file = '')
	{
		$already_replaced = array();
		$css = $content."\n\n\n";
		if(preg_match_all('#behavior[ ]?:[ ]?url\([\'"]?([\w_\-/\.]+)[\'"]?\)#',$css,$matches))
		{
			foreach($matches[1] as $match)
			{
				if(!preg_match('#^/#',$match) && !in_array($match,$already_replaced))
				{
					$relative_match = preg_replace('#^\./#','',$match);
					$css = preg_replace('#'.$match.'#',$relative_match,$css);
					$relative_absolute = $_SERVER['REQUEST_URI'];
					$behavior_path = $relative_absolute.'/../'.$relative_match;
					$behavior_path = str_replace('//','/',$behavior_path);
					$css = preg_replace('#'.$relative_match.'#',$behavior_path,$css);
					array_push($already_replaced,$match);
				}
			}
		}
			
		if(preg_match_all('#url\([\'"]?([\w_\-/\.]+)[\'"]?\)#',$css,$matches))
		{
			foreach($matches[1] as $match)
			{
				if(!preg_match('#^/#',$match) && !in_array($match,$already_replaced))
				{
					$path_search = explode('/',$file);
					unset($path_search[count($path_search)-1]);
					$path = implode('/',$path_search).'/';
					$css = preg_replace('#'.$match.'#',$path.$match,$css);
					array_push($already_replaced,$match);
				}
			}
		}
			
		//$css = preg_replace('#url\([\'"]?(\w_\-/\.]+)[\'"]?\)#',$path.'$1',$css);
			
		return $css;
	}
	private function _getOptimizedSource()
	{
		$output = '';
		sort($this->_cssFiles['folders']);
		sort($this->_cssFiles['files']);
		sort($this->_lessFiles['folders']);
		sort($this->_lessFiles['files']);
		foreach($this->_cssFiles as $group)
		{
			foreach($group as $file) $output .= $this->_generateExactCssUrl(file_get_contents($file), $file);
		}
		if(count($this->_lessFiles['folders']) > 0 || count($this->_lessFiles['files']) > 0)
		{
			require_once 'lessc.inc.php';
			$less = new lessc();
			$less_output = '';
			foreach($this->_lessFiles as $group)
			{
				foreach($group as $file) $less_output .=  file_get_contents($file);
			}
			$output .= $this->_generateExactCssUrl($less->parse($less_output), $file);
		}
		
		$output = str_replace("\n","",$output);
		return $output;
	}
	
	private function _getSource()
	{
		return file_get_contents('css-packer.css');
	}
	
	private function _getCss()
	{
		if(!$this->_isCache()) 
		{
			return $this->_generateSource();
		}
		else
		{
			return $this->_getSource();
		}
	}
}