<?php
/**
 * CSS PACKER
 * --------------------------
 * @author : Léo CHUZEL
 * 
 * define CACHE_CSS_ENABLE as true or false (boolean)
 */

// Default cache disabled
$cacheEnabled = false;

// Check if the constant CACHE_CSS_ENABLE is at true
if(defined('CACHE_CSS_ENABLE')){
	$cacheEnabled = (is_bool(CACHE_CSS_ENABLE)) ? CACHE_CSS_ENABLE : false;
}
// Include CssPacker class
include('CssPacker.php');

// Instancier CssPacker
$css = new CssPacker();

// Choisir le cache - si TRUE, le script s'arrete et affiche le fichier généré
$css->setCache($cacheEnabled);

// Blacklist
$css->setBlacklistFiles(array(
	"print.css"
));

echo $css->display();