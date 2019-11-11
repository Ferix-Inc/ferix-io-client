# Ferix IOとは？

Ferix IOは環境情報を「見える化」するプラットフォームです。

Raspberry Piとオムロン環境センサがあれば、温度、湿度、照度、気圧、騒音、CO2濃度(相当)を計測し、リアルタイムから過去にさかのぼり、その量と変化をブラウザ経由で見ることができます。
家やオフィス、病院や学校などあらゆる場所で簡単に導入できます。

なお、現在開発版のため様々なご不便あるかと思いますが、何卒ご諒恕のほどよろしくお願い申し上げます。

# サインアップする

まず[公式WEBサイト]( https://www.ferix.io/ )からサインアップをお願いします。
メールアドレスと任意のパスワードを送信すると、検証用コードがメールアドレスに送られます。そのコードを使用して、サインアップを完了してください。
その日から、Ferix IOのすべてのサービスをご利用いただけます。

# 使用例

## 環境

基本的にはRaspberry Piでの使用を想定しておりますが、Ferix IOのWeb APIにデータを送信できるなら、方法・デバイスは問いません。

ただ今回のサンプルではPHPスクリプトとRaspberry Piを主に使用してご紹介したいと思いますので、その環境の一例としてご紹介します。

- Raspberry Pi 3 B+
- Raspbian GNU/Linux 10 (buster)
- PHP 7.2
- Python 3.7.3



## データを送信する準備

Ferix IOでは、データを取得するセンサー等の単位を**「センサー・ユニット(または単にユニット)」**と呼び、そのセンサー・ユニットのグループを**「母艦」**と呼んでいます。

例えばオムロン環境センサとRaspberry Piを組み合わせて使用する場合は、オムロン環境センサーが**センサー・ユニット**に当たり、Raspberry Piが**母艦**に当たります。

複数のオムロン環境センサーや、その他センサーを同時に使いたい場合、Ferix IOに送信するデバイスはRaspberry Pi一台というとき、**母艦というのはグループの単位**として使用され、ユニットはそのグループ(母艦)に必ず属することになります。

たとえば一つのRaspberry Piで、複数のオムロン環境センサを使う場合などは、このようなグループ化が役に立つと思います。

### 母艦とユニットを作る

まず母艦とユニットを作ります。
Ferix IOのコンソール画面を開き、[Unit Settings](https://app.ferix.io/setting_units)ページを開いてください。
「新しい母艦ID」というフォームから、任意のIDを持つ母艦を作ります。今回は仮にmothershipというIDを持つ母艦を作ります。「母艦を追加する」ボタンをクリックすると、新しいペインが追加されると思います。

新しく母艦が作られましたが、まだユニットはつくられていません。
この母艦にユニットを作ります。
母艦の表示名と、センサーユニットを設定します。母艦表示名にはコンソール上で表示するための母艦名を設定します。センサーユニットには、その母艦に所属するセンサーユニットを設定します。
センサーユニットのIDは英数字、ハイアン、アンダーバーが使用でき、任意の名前をつけることができます。このセンサーユニットとカンマを挟んで、ユニット名を設定することができます。

変更後は、必ず「変更を保存する」をクリックして、変更箇所を確実にしてください。

## データ送信の一例(PHPの場合)

最もシンプルな例として、(実用性はありませんが)Raspberry PiからUNIX時間だけ送信してみましょう。今回はPHPによるサンプルコードを掲載しておきます。
PHP7.2以上のインストールを先に行っておいてください。

また、事前に先程設定した母艦IDとユニットIDのほか、APIキーを確認しておいてください。
必要な情報は以下の通りです。

- ユーザーID

- 母艦ID

- ユニットID

- APIキー

  

```php
// 必要なライブラリの読み込み
require_once("FerixIo.php");

// ユーザーID
$userId = "sample";

// 母艦ID
$mothershipId="m_1";

// ユニットID
$unitId = ここにユニットIDを入力

// APIキー
$ferixIoAPIKey = ここにAPIキーを入力

// インスタンスの作成
$ferix = new FerixIo($userId, $mothershipId, $ferixIoAPIKey);

// 送信するデータを連想配列でまとめる(今回は時間だけ設定しています)。
$params = ["unit_id"=>$unitId,// 必ず必要です
			"_time"=> time
          ];
// データの送信
$result = $ferix->sendEnvData($params);

// 無事送信されたかの確認
if ($result["header"]["http_code"] === 200) {
    // 成功の場合
    print date("Y-m-d H:i:s") . " [INFO] Success.\n";
} else {
    // 失敗の場合
    print date("Y-m-d H:i:s") . " [ERROR] Failed to send data.\n";
}
```

このとき、連想配列`$params`に入るデータはβ版の現在のところ**限定されることにご注意ください**(今後改良予定です)。
現在最大以下のようなプロパティを設定することができます。

```php
$params = [
    "unit_id"=>$unitId,
    "_time" => time, //時間
    "temperature"=>26.5, //気温
    "eco2"=> 240, // CO2(相当)
    "atm"=> 1100, // 気圧
    "etvoc"=>87, // TVOC相当
    "humidity"=>45, // 湿度
    "illumination"=>60,//照度
    "noise"=>45 // 騒音
];

```

## オムロン環境センサー(USBタイプ)を使う場合

あらかじめオムロン環境センサー(USBタイプ、形2JCIE-BU01)をご用意して、そのBIDを確認しておいてください。
オムロン環境センサーのパッケージの中に、BIDの書かれた紙が一緒に入っていると思います。

BIDはユニットIDとして、Ferix IOのコンソールから以下のように登録しておきます。

```php
require_once("FerixIo.php");


// ユーザーID
$userId = "test1";

// 母艦ID
$mothershipId="m_1";

// APIキー
$ferixIoAPIKey = ここにAPIキーを入力;

// インスタンス
$ferix = new FerixIo($userId, $mothershipId, $ferixIoAPIKey);

// オムロン環境センサからデータを取得して、送信する
$result = $ferix->sendOmronData();

// 無事に送信できたかを確認する
if ($result["header"]["http_code"] === 200) {
    // 成功
    print "[INFO] Success - Environmental data has been sent.\n";
} else {
    // 失敗
    print "[ERROR] Failed to send environmental data.\n";
}

```



# データ送信の一例(Pythonの一例)

ユーザーの新規登録をして、母艦ID、ユニットIDの登録も済ませておいてください。必要な情報は、ユーザーID、母艦ID、ユニットID、それにAPIキーの４つとなります。

pythonディレクトリの中のapp.pyがサンプル用のコードになります。基本的にはferix_io_client.pyを読み込んで使用してもらえれば簡単にデータをアップロードできます。

PHP版と違いとして、オムロン環境センサを使用する場合はユニットIDも記載してもらう必要がありますので、こちにはBIDをあらかじめご用意しておいてください。

```python
import time
import datetime
from ferix_io_client import FerixIoClient


# ユーザーID
USER_ID = "sample"

# 母艦ID
MOTHERSHIP_ID = "m_1"

# APIキー
API_KEY = ここにAPIキーを入力

# スリープする時間(秒。10秒以下にしないでください)
SLEEP_TIME = 10

# ユニットID
UNIT_ID = ここにユニットIDを入力

f = FerixIoClient(USER_ID,MOTHERSHIP_ID,API_KEY)

while True:
    res = f.send_omron_data(UNIT_ID)

    if res.status_code == 200:
        print("[*] Success: {}".format(datetime.datetime.now().strftime("%Y/%m/%d %H:%M:%S")))
    else:
        print("[*] Error {0}: {1}".format(res.status_code, res.text))

    # 古いデータがある場合送信する。
    res_old = f.sendOldData(200)

    time.sleep(10)
```

オムロン環境センサを使わずに、任意のデータを送信したい場合は以下のように使います。

```python

import time
import datetime
from ferix_io_client import FerixIoClient


# ユーザーID
USER_ID = "sample"

# 母艦ID
MOTHERSHIP_ID = "m_1"

# APIキー
API_KEY = ここにAPIキーを入力

# スリープする時間(秒。10秒以下にしないでください)
SLEEP_TIME = 10

# ユニットID
UNIT_ID = "u_1"

f = FerixIoClient(USER_ID,MOTHERSHIP_ID,API_KEY)

while True:
    params = {
        "_time":int(datetime.now().timestamp()),
        "unit_id": UNIT_ID,
        "mothership_id": MOTHERSHIP_ID,
        "temperature": 26.5
    }
    res = f.send(params)

    if res.status_code == 200:
        print("[*] Success: {}".format(datetime.datetime.now().strftime("%Y/%m/%d %H:%M:%S")))
    else:
        print("[*] Error {0}: {1}".format(res.status_code, res.text))


    time.sleep(10)


```



Python版の説明は以上となります。



# FAQ

Qiitaにて更新予定。
