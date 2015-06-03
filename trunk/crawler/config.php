<?php
// ------------------------------
// クロール先情報
// ------------------------------
// サイトURL
$crawl_url_base = "http://www.kaigokensaku.jp/{prefcd}/";
// 検索URL
$crawl_url_search_pref = "index.php?action_kouhyou_pref_search_list_list=true&PrefCd={prefcd}";
set_time_limit(0);
ini_set("memory_limit","512M");
// ------------------------------
// DB接続情報
// ------------------------------

/* Staging
$dbinfo = array(
    "server" => "127.0.0.1",
    "username" => "lincon",
    "password" => "8uWRQfv876",
    "database" => "lincon_crawler"
);
*/

$dbinfo = array(
    "server" => "127.0.0.1",
    "username" => "root",
    "password" => "",
    "database" => "lincon"
);

// ------------------------------
// ログ情報
// ------------------------------
$dir_logfile = "./log";
$debug_mode = true;

// ------------------------------
// PHP設定
// ------------------------------
date_default_timezone_set('Asia/Tokyo');

// ------------------------------
// サービスコード
// ------------------------------
$arr_servicecd = array(
		// 居宅介護支援
		array(
			"kaigokensaku" => "023",
			"wam" => "50"
		),
		// 訪問介護
		array(
			"kaigokensaku" => "001",
			"wam" => "1"
		),
		// 訪問入浴
		array(
			"kaigokensaku" => "003",
			"wam" => "2"
		),
		// 訪問看護
		array(
			"kaigokensaku" => "004",
			"wam" => "3"
		),
		// 訪問リハビリ
		array(
			"kaigokensaku" => "005",
			"wam" => "4"
		),
		// 夜間対応型訪問介護
		array(
			"kaigokensaku" => "002",
			"wam" => "91"
		),
		// 通所介護
		array(
			"kaigokensaku" => "006",
			"wam" => "11"
		),
		// 通所リハビリ
		array(
			"kaigokensaku" => "009",
			"wam" => "12"
		),
		// 療養通所介護
		array(
			"kaigokensaku" => "008",
			"wam" => "10"
		),
		// 認知症対応型通所介護
		array(
			"kaigokensaku" => "007",
			"wam" => "92"
		),
		// 小規模多機能型居宅介護
		array(
			"kaigokensaku" => "021",
			"wam" => "93"
		),
		// 短期入所生活介護
		array(
			"kaigokensaku" => "025",
			"wam" => "21"
		),
		// 短期入所療養介護
		array(
			"kaigokensaku" => "030",
			"wam" => "22"
		),
		// 介護老人福祉施設
		array(
			"kaigokensaku" => "024",
			"wam" => "61"
		),
		// 介護老人保健施設
		array(
			"kaigokensaku" => "027",
			"wam" => "62"
		),
		// 介護療養型医療施設
		array(
			"kaigokensaku" => "029",
			"wam" => "63"
		),
		// 特定施設入居者生活介護
		array(
			"kaigokensaku" => "010",
			"wam" => "32"
		),
		// 認知症対応型共同生活介護
		array(
			"kaigokensaku" => "022",
			"wam" => "96"
		),
		// 地域密着型介護老人福祉施設入居者生活介護
		array(
			"kaigokensaku" => "026",
			"wam" => "94"
		),
		// 地域密着型特定施設入居者生活介護
		array(
			"kaigokensaku" => "012",
			"wam" => "95"
		),
		// 福祉用具貸与
		array(
			"kaigokensaku" => "019",
			"wam" => "41"
		),
		// 特定福祉用具販売
		array(
			"kaigokensaku" => "020",
			"wam" => "65"
		)
	);
?>
