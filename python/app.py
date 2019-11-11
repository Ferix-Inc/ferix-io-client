#!/usr/bin/python3
# -*- coding: utf-8 -*-


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



