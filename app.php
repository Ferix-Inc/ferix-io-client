<?php
/**
 * FerixIOクライアント用アプリ
 * オムロン環境センサのサンプルアプリ
 */

require_once("FerixIo.php");


// ユーザーID
$userId = "test1";
// $userId = "test_res";

// 母艦ID
$mothershipId="mothership";

// APIキー
$ferixIoAPIKey = "DLcF2Z45XpHEyLpSRDigW+zQdnVtTI99de8JgadN3nY=";

// アップロードのスパン。10秒以下には設定しないでください。
$waitingTime = 10;


// インスタンス
$ferix = new FerixIo($userId, $mothershipId, $ferixIoAPIKey);


// データ送信のためのメインループ
while(true) {

	// オムロン環境センサからデータを取得して、送信する
	$result = $ferix->sendOmronData();

	if (isset($result["header"]["http_code"]) && $result["header"]["http_code"] === 200) {
		// 成功
		print date("Y-m-d H:i:s") . " [INFO] Success - Environmental data has been sent.\n";
	} else {
		// 失敗
		print $result;
		print date("Y-m-d H:i:s") . " [ERROR] Failed to send environmental data.\n";
	}

	// 指定された時間だけ待機
	sleep($waitingTime);
}


