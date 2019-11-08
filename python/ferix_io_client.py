#!/usr/bin/python3
# -*- coding: utf-8 -*-

import json
import subprocess
import time
from datetime import datetime
import pickle
import os


class FerixIoClient():
    def __init__(self, user_id, mothership_id,api_key):
        self.api_key = api_key
        self.user_id = user_id
        self.mothership_id = mothership_id
        self.assets = []
        self.log_pickle = os.getcwd() + "/logs/log.pickle"

        # API
        self.api_base_url = "https://api.ferix.io/api/v1/"
        self.api_time_series = self.api_base_url + "metrics/" + self.user_id
        self.api_config = self.api_base_url + "users/" + self.user_id
        
        self.requirement_status = False
        self.headers = {
            'Content-Type':'application/json',
            'X-API-KEY':self.api_key
        }

        try:
            import requests
            self.requests = requests
        except ImportError:
            pass


    def send(self, params):
        """データの送信"""
        param_array=[]
        param_array.append(params)
        r = self.requests.post(self.api_time_series, json = param_array, headers = self.headers)
        return r

    def send_omron_data(self, omron_bid):
        omron_arr = self.get_omron_env(omron_bid)

        param_array = [
            {
                "mothership_id":self.mothership_id,
                "unit_id": omron_bid,
                "_time": omron_arr[0],
                "temperature": 19.4,
                "humidity": omron_arr[2],
                "illumination": omron_arr[3],
                "atm": omron_arr[4],
                "noise": omron_arr[5],
                "etvoc": omron_arr[6],
                "eco2": omron_arr[7],
            }
        ]
        r = self.requests.post(self.api_time_series, json = param_array, headers = self.headers)
        if r.status_code != 200:
            # 送信失敗時には、データを保存する
            self.saveLogs(param_array[0])
        return r

    def saveLogs(self,param):

        if (os.path.exists(self.log_pickle) == False):
            with open(self.log_pickle, 'wb') as f:
                pickle.dump([] , f)

        with open(self.log_pickle, 'rb') as p:
            data = pickle.load(p)

        data.append(param)

        with open(self.log_pickle, 'wb') as f:
            pickle.dump(data , f)

    def sendOldData(self, limit_num):
        """一時的に保存されたデータを送信"""
        if (os.path.exists(self.log_pickle) ==False):
            return False
                
        with open(self.log_pickle, 'rb') as p:
            params = pickle.load(p)
        if len(params) == 0:
            return False

        reserve = []
        old_data=[]

        for i, param in enumerate(params):
            if i < limit_num:
                old_data.append(param)
            else:
                reserve.append(param)
        
        with open(self.log_pickle, 'wb') as f:
            pickle.dump(reserve , f)

        r = self.requests.post(self.api_time_series, json = old_data, headers = self.headers)
        return r


    def get_omron_env(self, omron_bid):
        """オムロンセンサのBIDをもとにデータを取得"""
        args = ['timeout','-k','10','3','gatttool','-t','random','-b',omron_bid,'--char-read','--handle=0x0059','2>/dev/null']

        while True:
            try:
                res = subprocess.check_output(args)

                hex = res.decode("utf8").split(":")[1].strip().split(" ")

                tempe = int(hex[2] + hex[1],16)* 1/100
                hum = int(hex[4] + hex[3],16)* 1/100
                illum = int(hex[6] + hex[5],16)
                atm = int(hex[10] +hex[9] +hex[8] + hex[7],16)* 1/1000
                dB = int(hex[12] + hex[11],16)* 1/100
                eTVOC = int(hex[14] + hex[13],16)
                eCO2 = int(hex[16] + hex[15],16)

                # データの検証
                if atm == 0:
                    continue

                break
            except Exception as e:
                print(e)
                time.sleep(2);
                subprocess.check_output(["sudo","hciconfig","hci0","down"])
                subprocess.check_output(["sudo","hciconfig","hci0","up"])


        now_ts = datetime.now().timestamp()

        return [int(now_ts), tempe,hum,illum,atm ,dB,eTVOC,eCO2]

    
    def get_config(self):
        """設定データを取得する"""

        r = self.requests.get(self.api_config, headers = self.headers)
        if r.status_code != 200:
            print("[*] Error {0}: Can't get configulation data.  {1}".format(r.status_code, r.text))
        else:
            self.requirement_status=True
        
        return r