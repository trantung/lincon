<?php
// ------------------------------
// 対象都道府県
// ------------------------------
if (!is_array($argv) || count($argv) < 2) {
	echo "no argument.";
	die;
}
$prefcd = $argv[1];
if (!$prefcd) {
	echo "invalid argument.\n";
	die;
}
// ------------------------------
// インクルード
// ------------------------------
include("config.php");
include("crawler.php");
Util::putlog("▼▼▼開始▼▼▼ : prefcd=".$prefcd, "info");
// Util::sendmail("クロール開始のアラート連絡先メールアドレスを記載", "クロール開始のアラート連絡先メールアドレスを記載", "[crawl]start", "prefcd=".$prefcd);

// ------------------------------
// クロール
// ------------------------------
$crawler = new Crawler();
$crawler->exec($prefcd);

Util::putlog("▲▲▲終了▲▲▲", "info");
// Util::sendmail("クロール終了のアラート連絡先メールアドレスを記載", "クロール終了のアラート連絡先メールアドレスを記載", "[crawl]end", "prefcd=".$prefcd);
?>
