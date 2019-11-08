<?php

/**
 * Ferix-io client class
 * Ferix-io Client API用クラス
 */

require_once("config.php");


class FerixIo {

    public $method = NULL;
    public $jsonDecodeFlag;
    public $config = NULL;
    public $expire = 0;
    public $expiresIn = Null;

    private $nextTime = 0;
    private $pendingTime = 0;// 待機時間、分単位で指定
    private $nextDataSendingTime=0;// unixタイムで次に送信する時間を保存


    /**
     * @constructor
     */
    public function __construct($userId, $mothershipId, $ferixIoAPIKey)
    {
        //メンバ
        $this->userId = $userId;
        $this->method = "POST";
        $this->mothershipId = $mothershipId;
        $this->pendingTime =PENDING_TIME;
        $this->assets=[];

        // 時系列APIのURL
        $this->API_TIME_SERIES_ROUTE = COMMON_API . "metrics/" . $userId;
        $this->API_CONFIG_ROUTE = COMMON_API . "users/" . $userId;
        $this->FERIX_IO_API_KEY = $ferixIoAPIKey;

        // センサーユニットの取得
        $this->getAssets();
    }
    
    /**
     * オムロン環境センサからデータを取得し、サーバーへ送信する
     *
     * @return void
     */
    public function sendOmronData(){
        
        // アップロード用のデータを整形
        $params = $this->makeUploadData($this->assets);

        if ($params == false){
            $this->printLog("Failed to fetch data. ");
            return false;
        }
        $result=NULL;
        
        if ($this->pendingTime > 0 ){
            // まとめてアップロードの場合
            $result = $this->pendingSetData("[".$params."]");
        } else {
            // 通常
            $jsonDecodeFlag=true;
            $upload_old=true;

            // キャッシュをクリアにする
            clearstatcache();
            if (USE_GZIP) {
                $result = $this->uploadGzipData("[".$params."]");
            } else {
                $result = $this->setData("[".$params."]",200,$jsonDecodeFlag, $upload_old);
                
            }
        }
        return $result;
    }

    /**
     * 連想配列にされた任意のデータを送信する
     *
     * @param array $paramsObj 送信する連想配列
     * @return array $result レスポンスデータ
     */
    public function sendEnvData($paramsObj) {
        if (!count($paramsObj)) $this->printLog("No data available: sendEnvData");

        // 母艦IDと時間をセット
        $paramsObj['mothership_id'] = $this->mothershipId;
        $paramsObj['_time'] = time();

        // JSON化
        $params = json_encode($paramsObj);

        // 通常
        $jsonDecodeFlag=true;
        $upload_old=true;

        // キャッシュをクリアにする
        clearstatcache();
        if (USE_GZIP) {
            $result = $this->uploadGzipData("[".$params."]");
        } else {
            $result = $this->setData("[".$params."]",200,$jsonDecodeFlag, $upload_old);
            
        }
        return $result;

    }

    /**
     * アセットの取得
     *
     * @return void
     */
    public function getAssets(){
        if ($this->chkExpire() === true) {
        
            // 母艦からUnitIDを取得
            $assets_tmp= json_decode($this->config['motherships'], true);
            $this->assets=[];// 初期化
            foreach ($assets_tmp as $a){
                if ($a["id"] == $this->mothershipId){
                    $waiting_time = $a["waiting_time"];
                    foreach($a['assets'] as $asset) {
                        $this->assets[] = $asset["asset_id"];
                    }
                }
            }
            if (count($this->assets) === 0){
                $this->printLog("Not config data exist: $this->mothershipId");
            }
            // 環境データ取得にリトライする回数
            // リトライ最大数を超えた場合「センサーユニットは正常に設置されていない」と解釈して残りのデータをアップ
            $this->retry_max = json_decode($this->config['client_config'], true)['retry_max'];
        }
    }



    /**
     * アップロード用データ作成
     * @return string $param アップ用にJson形式でまとめた環境データ
     */
    function makeUploadData($assets){
        $mothershipId = $this->mothershipId;
        $fparams="";
        $errMessage=[];
        $errUnit=[];
        // 環境データを取得
        
        foreach($this->assets as $macAddress){
            while(true) {
                // 環境データ取得
                $results = $this->getEnvData($macAddress);
                if ($results[0] === false){
                    $this->printLog("Can't get env data: BID : $results[1]");

                    // リトライ回数を超えたと解釈して飛ばす
                    break 1;//次のforeachへ
                }
                list($tempe,$hum,$illum,$atm,$dB,$eTVOC,$eCO2) = $results;

                // 気圧がゼロなら不自然なデータなのでコンティニュー
                if ($atm == 0) continue;

                // 現在時刻の取得
                $time = time();
                if($fparams !== "") {
                    $fparams .= ",";
                }
                $param = <<<PARAM
                {
                    "mothership_id": "$mothershipId",
                    "unit_id": "$macAddress",
                    "_time": "$time",
                    "temperature":"$tempe",
                    "eco2":"$eCO2",
                    "atm":"$atm",
                    "etvoc":"$eTVOC",
                    "humidity":"$hum",
                    "illumination":"$illum",
                    "noise":"$dB"
                }
PARAM;
                $fparams .= $param;
                break 1;//whileだけから抜ける。次のforeachへ

            }
        }
        // エラーがある場合は報告
        // if (count($errMessage) > 0){
        //     print join("\n",$errMessage);
        // }

        if ($fparams == "") return false;
        return $fparams;
    }

    /**
     * ログを表示する
     *
     * @param string $message 表示するエラーや情報メッセージ
     * @param integer $type メッセージのタイプ。デフォルトの0ではエラー、1ならINFO。
     * @return void
     */
    private function printLog($message,$type=0) {
        if ($type == 1) {
            print date("Y-m-d H:i:s") ." [INFO] " .  $message . "\n";
        } elseif ($type==0) {
            print date("Y-m-d H:i:s") ." [ERROR] " .  $message . "\n";
        }
    }


    /**
     * オムロン環境センサからデータを取得
     * @param none
     * @return array 取得した環境データの配列
     */
    private function getEnvData($macAddress){

        $output=[];
        $cnt=1;
        while(true) {
            $com = 'timeout -k 10 3 gatttool -t random -b '.$macAddress . ' --char-read --handle=0x0059 2>/dev/null';

            exec($com,$output, $returnVar);
            if($returnVar !== 0){
                $this->printLog("Command failed to execute correctly. : gatttool");

                sleep(1);
                system("sudo hciconfig hci0 down");
                system("sudo hciconfig hci0 up");
                
                // return false;
            } else {
                break;
            }

            if ($cnt >= $this->retry_max) {
                $this->printLog("Failed to catch environmental data.");
                return [false,$macAddress];
            }
            $cnt++;
        }

        // エラー時の万一の処理
        $str = $output[0];
        if (strpos($str,"error") !== false){
            return [false,$macAddress];
        }

        // データ整形
        $hex = explode(" ",trim(explode(":",$str)[1]));

        $tempe = hexdec($hex[2] . $hex[1]) * 1/100;//温度(temperature) degC
        $hum = hexdec($hex[4] . $hex[3]) * 1/100;//湿度(humidity)%RH
        $illum = hexdec($hex[6] . $hex[5]) ;//照度(illumination)1 lx
        $atm = hexdec($hex[10] . $hex[9] . $hex[8] . $hex[7]) * 1/1000;//気圧(atmospheric pressure)1 hPa
        $dB = hexdec($hex[12] . $hex[11]) * 1/100;//騒音(dB)
        $eTVOC = hexdec($hex[14] . $hex[13]) ;//eTVOC(VOCにカテゴライズされるガス種の濃度、ホルムアルデヒドやアルコール、タバコの煙)ppb
        $eCO2 = hexdec($hex[16] . $hex[15]) ;//eCO2（equivalent CO2）値は、TVOC値から算出される二酸化炭素濃度相当値であり、二酸化炭素濃度を直接検出ではない(1ppm)

        return [$tempe,$hum,$illum,$atm,$dB,$eTVOC,$eCO2];
    }

    /**
     * コンフィグデータの取得
     * 
     * @return Array $arr 取得したJsonデータをデコード済みコンフィグ
     */
    function getConfig()
    {
        // 必要なヘッダー要素をまとめる
        $header = [];
        $header[] = "Content-Type:application/json";
        $header[] = "X-API-KEY:".$this->FERIX_IO_API_KEY;

        list($arr,$info) = $this->exeCurl($this->API_CONFIG_ROUTE, $header, "GET","",true);
        if ($info["http_code"] != 200) {
            $this->printLog("Can't get configulation data. Error code : ". $info["http_code"]);
        }
        
        return $arr;

            
    }

    /**
     * ローカルに保存されたコンフィグログを読み込む
     * 万一ネットに繋げない環境においては、このコンフィグを使用する
     *
     * @return void
     */
    public function readConfigLog(){
        // キャッシュをクリアにする
        clearstatcache();

        // ファイルがなければ作る
	    if (!file_exists(CONFIG_SAVE_LOG)) file_put_contents(CONFIG_SAVE_LOG,"");
        $jsonstr = file_get_contents(CONFIG_SAVE_LOG);
        $this->config = json_decode($jsonstr, true);
    }

    /**
     * ローカルのコンフィグファイルに、コンフィグ情報を記録する
     * 
     * @param [array] $config コンフィグデータを含んだ連想配列
     * @return void
     */
    public function putConfigLog($config){
        // ファイルがなければ作る
        if (!file_exists(CONFIG_SAVE_LOG)) 
            file_put_contents(CONFIG_SAVE_LOG,"");

        if (file_exists(CONFIG_SAVE_LOG))
            chmod(CONFIG_SAVE_LOG, 0777);

        $config_json =  json_encode($config);
        file_put_contents(CONFIG_SAVE_LOG, $config_json, LOCK_EX);
    } 



    /**
     * 受け取ったデータをgzip圧縮して送信。
     *
     * @return void
     */
    function uploadGzipData($param){

        //json形式のデータを$json_strに集める
        $gzdata = gzencode($param, 9);
        

        // 必要なヘッダー要素をまとめる
        $header = [];
        $header[] = "Content-Type:application/json";
        $header[] = "X-API-KEY:".$this->FERIX_IO_API_KEY;
        $header[] = "Content-Encoding:gzip";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->API_TIME_SERIES_ROUTE);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 実行結果を文字列で返す
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // サーバー証明書の検証を行わない
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header); 
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST'); // methodの指定
        
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.109 Safari/537.36"); 
        curl_setopt($ch,CURLOPT_DNS_USE_GLOBAL_CACHE, false );
        curl_setopt($ch,CURLOPT_DNS_CACHE_TIMEOUT, 2 );
        
        
        //リダイレクト関係
        //Locationをたどる
        curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);
        //最大何回リダイレクトをたどるか
        curl_setopt($ch,CURLOPT_MAXREDIRS,30);
        //リダイレクトの際にヘッダのRefererを自動的に追加させる
        curl_setopt($ch,CURLOPT_AUTOREFERER,true);
        
        
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, TRUE); 

        curl_setopt($ch, CURLOPT_POSTFIELDS, $gzdata);
        
        
        $arr = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);


        // httpステータスコードが予定と違う時エラー
        if ($info["http_code"] != 200) {
            $this->printLog("Status code : " . $info["http_code"]);

            // データの保存
            // 不要な文字列の削除
            $param  = substr($param, 1, -1 );


            // 空でなければカンマをつける。
            if(filesize(TEMP_SAVE_LOG) !== 0){
                $param = "," . $param;
            }

            file_put_contents(TEMP_SAVE_LOG, $param, FILE_APPEND);

            return $arr;
        }

        return ['header'=>$info,'body'=>$arr];

    }



    /**
     * 古いデータの分割送信
     *
     * @return Boolean 成功の場合true
     */
    public function sendOldData(){
        // filesize関数は一度使うとキャッシュされるので
        // キャッシュをクリアにする
        clearstatcache();
        sleep(1);
        // ファイルがなければ作る
        if (!file_exists(TEMP_SAVE_LOG)) file_put_contents(TEMP_SAVE_LOG,"");


        // 最新データのアップに成功していれば過去ログも続けてアップする。
        // 過去データがゼロなら正常に終了
        $file_size = filesize(TEMP_SAVE_LOG);
        if($file_size === 0) 
            return true;


        // スループットに余裕をもたせるため、少し待つ。
        sleep(1);

        $old_data = file_get_contents(TEMP_SAVE_LOG);

        $this->printLog("Old file size: " . $file_size,1);

        $old_data = "[". $old_data . "]";
        $json_old_data = json_decode($old_data, true);

        $count=0;
        $up_list=[];
        $pending_list=[];
        foreach ($json_old_data as $t){
            
            $count++;
            if ($count <= MAX_OLD_DATA) {
                array_push($up_list,$t);
                
            } else {
                array_push($pending_list,$t);
            }
        }

        $up_str = json_encode($up_list);
        $this->printLog("Upload data size: " . strlen($up_str)/1000 . "KB",1);

        // 過去データをアップロード
		$jsonDecodeFlag=true;
		$uploadOld=false;
        $resultOld = $this->setData($up_str,200, $jsonDecodeFlag, $uploadOld);

        if ($resultOld["header"]["http_code"] === 200){
            $this->printLog("Old data has been successfully sent.",1);


            $pneding_str = json_encode($pending_list);
            if (strlen($pneding_str) > 0){
                $pneding_str  = substr( $pneding_str , 1 , -1 );
            }

            // 蓄積データの保存
            file_put_contents(TEMP_SAVE_LOG, $pneding_str);
        }

        return true;


    }


    /**
     * データを実際にデータテーブルに送信する
     * @params String    $param  Assetに登録したいデータ
     */
    function setData($param,$http_code,$jsonDecodeFlag=true,$upload_old=false)
    {


        // 必要なヘッダー要素をまとめる
        $header = [];
        $header[] = "Content-Type:application/json";
        $header[] = "X-API-KEY:".$this->FERIX_IO_API_KEY;

        // curl実行
        list($arr,$info) = $this->exeCurl($this->API_TIME_SERIES_ROUTE,$header,$this->method,$param,$jsonDecodeFlag);

        // httpステータスコードが予定と違う時エラー
        if ($info["http_code"] != $http_code) {

            // データの保存
            // 不要な文字列の削除
            $param  = substr($param, 1, -1 );


            // 空でなければカンマをつける。
            if(filesize(TEMP_SAVE_LOG) !== 0){
                $param = "," . $param;
            }

            file_put_contents(TEMP_SAVE_LOG, $param, FILE_APPEND);

            return $arr;
        }

        if ($upload_old === true) {
            // 過去データ送信
		    $this->sendOldData();
        }

        return ['header'=>$info,'body'=>$arr];
    
    }//setData



    /**
     * 期限チェック
     * 
     */
    function chkExpire(){
        $current_time = time();

        if ($current_time >= $this->expire){
            $result = $this->getConfig();
            // 正常にconfigデータが取得できなかった場合
            if (!is_array($result)){
                $this->printLog("Can't get config.");
                $this->readConfigLog();
            } else {
                // 通常通りにconfigデータを取得できた場合
                $this->config = $result;
                $this->putConfigLog($result);
            }


            $expiresIn = json_decode($this->config['client_config'], true)['expires_in'];

            // 分単位を秒単位に戻してメンバに登録
            $this->expiresIn = $expiresIn * 60;
            $this->expire = time() + $this->expiresIn;

            $this->printLog("Successfully updated configuration settings.",1);
            return true;
        } else {
            // 更新不要
            return false;
        }
    }



    function exeCurl($url,$header,$method,$param,$jsonDecodeFlag=true){
    
        $arr=[];
        $information=[];
        try{
            // cURLセッションを初期化
            $ch = curl_init();
        
            curl_setopt($ch, CURLOPT_URL, $url); // 取得するURLを指定
        
            // cURLのその他設定
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 実行結果を文字列で返す
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // サーバー証明書の検証を行わない
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header); // リクエストにヘッダーを含める
        
        
            // ヘッダも出力したい場合
            //curl_setopt($ch, CURLOPT_HEADER, true); 
        
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method); // methodの指定
        
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.109 Safari/537.36"); 
        
            curl_setopt($ch,CURLOPT_DNS_USE_GLOBAL_CACHE, false );
            curl_setopt($ch,CURLOPT_DNS_CACHE_TIMEOUT, 2 );
        
            //リダイレクト関係
            //Locationをたどる
            curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);
            //最大何回リダイレクトをたどるか
            curl_setopt($ch,CURLOPT_MAXREDIRS,30);
            //リダイレクトの際にヘッダのRefererを自動的に追加させる
            curl_setopt($ch,CURLOPT_AUTOREFERER,true);
        

            curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
        
            $response =  curl_exec($ch);
            
            
            // Jsonデコードするか否か
            if ($jsonDecodeFlag) {
                $arr = json_decode($response,true);
            } else {
                $arr[] = $response;
            }
        
            $information = curl_getinfo($ch);
                
            // セッションを終了
            curl_close($ch);

        } catch(Exception $e){
            $this->printLog($e->getMessage());
        }

    
        return [$arr,$information];
    
    }
    


    /**
     * エラーメッセージをログに書き込む
     *
     * @param string $err_message エラーメッセージ
     * @return void
     */
    public function putErrorLog($err_message){
        print $err_message;
        if (file_exists(ERR_LOG)) chmod(ERR_LOG, 0777);
        file_put_contents(ERR_LOG, $err_message,FILE_APPEND);

    } 

    
    /**
     * エラーログを指定されたAPIに飛ばす
     * 現在使用されていません。
     *
     * @param string $errMessage エラーメッセージ
     * @param string $unitId ユニットID(あれば)
     * @return void
     */
    public function putErrorToDB($errMessage,$unitId="") {


        // 期限チェック
        if ($this->nextTime === 0) {
            $this->nextTime=time() + NEXT_SENDING_TIME * 60;
            return;
        }
        if (time() < $this->nextTime){
            return;
        }

        // unitIdが空なら、母艦ID
        if ($unitId === "") $unitId = $this->mothershipId;
        $time = time();
        $param = <<<JSON
        {
            "mothership_id": "{$this->mothershipId}",
            "_time": "$time",
            "unit_id" : "$unitId",
            "user_id":"{$this->userId}",
            "message": "$errMessage"
        }
JSON;

        // 必要なヘッダー要素をまとめる
        $header = [];
        $header[] = "Content-Type:application/json";
        $header[] = "X-API-KEY:".$this->FERIX_IO_API_KEY;
        $errorApiUrl= COMMON_API . "/error";
        list($arr,$info) = $this->exeCurl($errorApiUrl, $header, "PUT", $param, true);

        // httpステータスコードが予定と違う時エラー
        if ($info["http_code"] != 200) {
            $this->printLog("Error: " . $info["http_code"]);
            return $arr;
        }
        
        // リセット
        $this->nextTime = 0;

    }

}// class

