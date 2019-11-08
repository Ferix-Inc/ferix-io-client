<?php
/**
 * FerixIOクライアント用サンプルアプリ
 * 
 */
require_once("./FerixIo.php");



// ユーザーID
$userId = "sample";

// 母艦ID
$mothershipId = "m-1";

// ユニットID
$unitId = "u-1";

// APIキー
$ferixIoAPIKey = "";

// アップロードのスパン。10秒以下には設定しないでください。
$waitingTime = 10;


// インスタンス生成
$ferix = new FerixIo($userId, $mothershipId, $ferixIoAPIKey);



// データ送信のためのメインループ
while(true) {

	$params = ["unit_id"=>$unitId,
				"temperature"=> mt_rand(24, 30)];

	$result = $ferix->sendEnvData($params);


	if (isset($result["header"]["http_code"]) && $result["header"]["http_code"] === 200) {
		// 成功
		print date("Y-m-d H:i:s") . " [INFO] Success - Environmental data has been sent.\n";
	} else {
		// 失敗
		print date("Y-m-d H:i:s") . " [ERROR] Failed to send environmental data.\n";
	}

	// 指定された時間だけ待機
	sleep($waitingTime);
}

