<?php

function my_mb_ucfirst($str) {
    $fc = mb_strtoupper(mb_substr($str, 0, 1));
    return str_replace("\n", '', $fc.mb_substr($str, 1));
}

function checkIncludeFile($path, $file = "") {
	if(!file_exists($_SERVER['DOCUMENT_ROOT'] . $path))
		mkdir($_SERVER['DOCUMENT_ROOT'] . $path, 0755, true);	
	if($file && !file_exists($_SERVER['DOCUMENT_ROOT'] . $path . "/" . $file))
		file_put_contents($_SERVER['DOCUMENT_ROOT'] . $path . "/" . $file, '');
	return;
}

function getArrSites($default = false) {
	$resSite = CSite::GetByID("s1");
	$domains = array();
	$arSite = $resSite->Fetch();
	if($default)
		return $arSite['SERVER_NAME'];
	if($arSite['DOMAINS'])
		$domains = explode("\n", $arSite['DOMAINS']);
	return $domains;
}

function arrSites($rusKey = false) {
	$domains = getArrSites();
	if(empty($domains))
		return;
		
	$arParams = array(
		"replace_space" => "-",
		"replace_other" => "-",
		"safe_chars" => ".",
	);
	
	$arResult = array();
	foreach($domains as $domain) {
		$domain = trim(str_replace("\n", '', $domain));
		if(trim($domain) == trim(getArrSites(true)))	
			continue;		
		$translit = Cutil::translit($domain, "ru", $arParams);
		$code = explode(".", $translit)[0];
		if($rusKey)
			$code = str_replace(array('-', ' '), "", mb_strtolower(explode(".", $domain)[0]));
		$arResult[$code]['DOMAIN'] = $domain;
		$arResult[$code]['CODE'] = $code;
		$arResult[$code]['CITY'] = my_mb_ucfirst(explode(".", $domain)[0]);
		if(explode(".", idn_to_ascii($domain))[0] == explode(".", $_SERVER['HTTP_HOST'])[0]) {
			$arResult[$code]['CURRENT'] = "Y";
		}
	}
	return $arResult;
}

function getCityNameEdost($id) {
	if (!CModule::IncludeModule('edost.locations'))
		return;
	$param = array(
		'id' => $id, // ID местоположения		
	);
	$res = CLocationsEDOST::GetCurrent($param);
	return $res;
}

function getCategoryDescription($SECTION_ID = '', $url = '') {
	global $APPLICATION;
	$IBLOCK_ID = SEO_IBLOCK_ID;
	$SECTION_ID = $SECTION_ID ? $SECTION_ID : SEO_SECTION_ID;
	$NAME = $url ? $url : $APPLICATION->GetCurPage();
	CModule::IncludeModule("iblock");
	$arrSelect = array('ID', 'DETAIL_TEXT');
	$arrFilter = array(
		'NAME' => $NAME,
		"SECTION_ID" => $SECTION_ID,
		"IBLOCK_ID" => $IBLOCK_ID,
	);
	$res = CIBlockElement::GetList(Array("SORT" => "DESC"), $arrFilter, false, Array("nPageSize"=>1), $arrSelect);
	if($ob = $res->GetNextElement()) {
		$arFields = $ob->GetFields();
		return $arFields['DETAIL_TEXT'];
	}
	return;
}

AddEventHandler("main", "OnEpilog",  "multiDomainSuccess");
function multiDomainSuccess() {
	global $APPLICATION;
	$IBLOCK_ID = SEO_IBLOCK_ID;
	$SECTION_ID = SEO_SECTION_ID;
	$NAME = $APPLICATION->GetCurPage();
	CModule::IncludeModule("iblock");
	$arrSelect = array('ID', 'PREVIEW_TEXT', 'PROPERTY_TITLE', 'PROPERTY_DESCRIPTION', 'PROPERTY_KEYWORDS', 'PROPERTY_H1', 'PROPERTY_ROBOTS');
	$arrFilter = array(
		'NAME' => $NAME,
		"SECTION_ID" => $SECTION_ID,
		"IBLOCK_ID" => $IBLOCK_ID,
	);
	$res = CIBlockElement::GetList(Array("SORT" => "DESC"), $arrFilter, false, Array("nPageSize"=>1), $arrSelect);
	if($ob = $res->GetNextElement()) {
		$arFields = $ob->GetFields();
		if(!empty($arFields['PROPERTY_TITLE_VALUE']))
			$APPLICATION->SetPageProperty("title", $arFields['PROPERTY_TITLE_VALUE']);			
		if(!empty($arFields['PROPERTY_KEYWORDS_VALUE']))
			$APPLICATION->SetPageProperty("keywords", $arFields['PROPERTY_KEYWORDS_VALUE']);
		if(!empty($arFields['PROPERTY_DESCRIPTION_VALUE']))
			$APPLICATION->SetPageProperty("description", $arFields['PROPERTY_DESCRIPTION_VALUE']);
		if(!empty($arFields['PROPERTY_H1_VALUE']))
			$APPLICATION->SetTitle($arFields['PROPERTY_H1_VALUE']);
		if(!empty($arFields['PROPERTY_ROBOTS_VALUE']))
			$APPLICATION->SetPageProperty("robots", $arFields['PROPERTY_ROBOTS_VALUE']);
	}
	return;
}

AddEventHandler("main", "OnBeforeProlog", "multiDomainPrepend");
function multiDomainPrepend() {
	if(isset($_POST['ID_SEO']) && !Empty($_POST['ID_SEO'])) {
		$_SESSION['ID_SEO'] = intVal($_POST['ID_SEO']); // for edost
		
	}
	define('SEO_CITY_2', getCityNameEdost($_SESSION['ID_SEO'])['city']);
	$domains = arrSites();
	
	if(empty($domains))
		return;
	
	$SECTION_ID = 0;
	$INCLUDE_FOLDER = 'default';
	$INCLUDE_PATH = '/local/include/' . $INCLUDE_FOLDER ;
	$CITY = '';
	$CITY_DATIV = '';
	$CITY_GENITIVE = '';
	$includeFolder = $_SERVER['DOCUMENT_ROOT'] . '/local/include/';	
	
	$IBLOCK_ID = 55;
	$arrSections = array();
	CModule::IncludeModule("iblock");
	$arrFilter = Array('IBLOCK_ID' => $IBLOCK_ID, 'SECTION_ID' => false);
	$sectionList = CIBlockSection::GetList(Array("SORT" => "ASC"), $arrFilter, false, Array("ID", "CODE", "NAME", "UF_NOMINATIVE", "UF_DATIVE", "UF_GENITIVE"));
	while($arrSection = $sectionList->GetNext()) {
		$arrSections[$arrSection["CODE"]] = $arrSection;
	}
	
	foreach($domains as $domain) {
		if(isset($arrSections[$domain["CODE"]]))
			continue;
		$bs = new CIBlockSection;
		$arFields = Array(
			"ACTIVE" => "Y",
			"IBLOCK_SECTION_ID" => 0,
			"IBLOCK_ID" => $IBLOCK_ID,
			"NAME" => $domain["CITY"],
			"CODE" => $domain["CODE"],
			"SORT" => 500,
		);
		$ID = ($bs->Add($arFields) > 0);
		if(!$ID)
			AddMessage2Log(print_r($bs->LAST_ERROR, 1) , "multiDomainSuccess");
	}
	
	foreach($domains as $domain) {
		if(isset($domain['CURRENT']) && $domain['CURRENT'] == "Y") {
			$INCLUDE_FOLDER = $domain['CODE'];
			$INCLUDE_PATH = '/local/include/' . $INCLUDE_FOLDER ;
			$CITY = $arrSections[$domain["CODE"]]["UF_NOMINATIVE"] ? $arrSections[$domain["CODE"]]["UF_NOMINATIVE"] : $domain['CITY'];
			$CITY_DATIV = $arrSections[$domain["CODE"]]["UF_DATIVE"];
			$CITY_GENITIVE = $arrSections[$domain["CODE"]]["UF_GENITIVE"];
			$SECTION_ID = $arrSections[$domain["CODE"]]["ID"];
		}
		if(!file_exists($includeFolder . $domain['CODE']))
			mkdir($includeFolder . $domain['CODE'], 0755);		
	}
	define('SEO_INCLUDE_FOLDER', $INCLUDE_FOLDER);
	define('SEO_INCLUDE_PATH', $INCLUDE_PATH);
	define('SEO_CITY', $CITY);
	define('SEO_CITY_DATIVE', $CITY_DATIV);
	define('SEO_CITY_GENITIVE', $CITY_GENITIVE);
	define('SEO_SECTION_ID', $SECTION_ID);
	define('SEO_IBLOCK_ID', $IBLOCK_ID);
	return;
}

AddEventHandler("main", "OnEndBufferContent", "ChangeTamplateCity");
function ChangeTamplateCity(&$content) {
	$arrCity = require_once $_SERVER['DOCUMENT_ROOT'] . '/local/include/domainList.php';
	$search = array('#CITY#', '#CITY_DATIVE#', '#CITY_GENITIVE#');
	$replace = array(SEO_CITY, SEO_CITY_DATIVE, SEO_CITY_GENITIVE);
	$replace_2 = array(SEO_CITY_2, $arrCity[SEO_CITY_2]['dative'], $arrCity[SEO_CITY_2]['genitive']);
	if(SEO_SECTION_ID)
		$content = str_replace($search, $replace, $content);
	else 
		$content = str_replace($search, $replace_2, $content);	
	return;
}

?>
