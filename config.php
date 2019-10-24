<?php

ini_set("display_errors",1);


// 環境センサからデータが取得されずエラーログをDBに飛ばす時間(分、デフォルトでは15)
define("NEXT_SENDING_TIME",15);

// エラーログの絶対パス
define("ERR_LOG",__DIR__ . "/log/error.log");

// 過去ログの絶対パス
define("TEMP_SAVE_LOG",__DIR__ . "/log/data.log");

// コンフィグ保存用ファイルの絶対パス
define("CONFIG_SAVE_LOG",__DIR__ . "/log/config.log");

// 分割して送信する過去のデータを何件に分割して送信するか(default=200)
define("MAX_OLD_DATA",600);

// 保留時間(分) データ取得だけしてローカルに保存し、ネットへのアップロードをn分間隔で行う
define("PENDING_TIME",0);

// gzip圧縮をして送信するか
define("USES_GZIP",true);

// 時系列APIのエンドポイント
define("COMMON_API","https://api.ferix.io/api/v1/");


