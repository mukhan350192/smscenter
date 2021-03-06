<?php

namespace App\Http\Controllers;

use App\Models\SMS;
use App\Models\User;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SMSController extends Controller
{
    // for identification type=1
    public function typeOne(Request $request)
    {

        $phone = $request->input('phone');
        $code = $request->input('code');
        $source = $request->input('source');

        $result['success'] = false;
        do {
            if (!$phone) {
                $result['message'] = 'Не передан телефон';
                break;
            }
            if (!$code) {
                $result['message'] = 'Не передан код';
                break;
            }
            if (!$source) {
                $result['message'] = 'Не передан ссылка на сайт';
                break;
            }

            DB::beginTransaction();
            $text = "Код для подтверждения на сайте $source $code";

            $smsID = SMS::insertGetId([
                'type' => 1,
                'text' => $text,
                'phone' => $phone,
                'status' => 100,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            if (!$smsID) {
                DB::rollBack();
                $result['message'] = 'Something went wrong';
                break;
            }

            $send = $this->sendSMS($smsID, $phone, $text);
            if ($send == true) {
                $result['success'] = true;
            } else {
                break;
            }
            DB::commit();

        } while (false);
        return response()->json($result);

    }

    // для подписа договора
    public function typeTwo(Request $request)
    {
        $phone = $request->input('phone');
        $amount = $request->input('amount');
        $leadID = $request->input('leadID');
        $url = $request->input('url');
        $result['success'] = false;

        do {
            if (!$phone) {
                $result['message'] = 'Не передан телефон';
                break;
            }

            if (!$amount) {
                $result['message'] = 'Не передан сумма';
                break;
            }

            if (!$leadID) {
                $result['message'] = 'Не передан id лида';
                break;
            }

            if (!$url) {
                $result['message'] = 'Не передан ссылка';
                break;
            }

            $text = 'Vam ODOBRENO ' . $amount . ' tg. Dlya polucheniya pereydite ' . $url;
            DB::beginTransaction();
            $smsID = SMS::insertGetId([
                'type' => 2,
                'text' => $text,
                'phone' => $phone,
                'status' => 100,
                'leadID' => $leadID,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            if (!$smsID) {
                DB::rollBack();
                $result['message'] = 'Something went wrong!';
                break;
            }
            $send = $this->sendSMS($smsID, $phone, $text);
            if ($send == false) {
                break;
            }
            $result['success'] = true;

            DB::commit();
        } while (false);
        return response()->json($result);

    }


    // отказ клиенту
    public function failureClient(Request $request)
    {
        $phone = $request->input('phone');
        $leadID = $request->input('leadID');
        $text = 'K sozhaleniju vam otkazano. Odnako vy mozhete obratitsja k nashim partnjoram , oni podberut dlja vas zajmy s vysokim odobreniem https://kaz-zaem.online/';
        $type = 3;
        $result['success'] = false;

        do {
            if (!$phone) {
                $result['message'] = 'Не передан телефон';
                break;
            }

            if (!$leadID) {
                $result['message'] = 'Не передан лид ';
            }

            $check = SMS::where('leadID', $leadID)->where('phone', $phone)->first();
            if (isset($check)) {
                break;
            }

            DB::beginTransaction();

            $smsID = SMS::insertGetId([
                'type' => $type,
                'text' => $text,
                'phone' => $phone,
                'status' => 100,
                'leadID' => $leadID,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);


            if (!$smsID) {
                DB::rollBack();
                $result['message'] = 'Something went wrong!';
                break;
            }
            $send = $this->sendSMS($smsID, $phone, $text);
            if ($send == false) {
                $result['message'] = 'Send SMS';
                break;
            }
            $result['success'] = true;

            DB::commit();
        } while (false);
        return response()->json($result);
    }

    // льготный период
    public function gracePeriod(Request $request)
    {
        $dealID = $request->input('dealID');
        $type = $request->input('type');
        $phone = $request->input('phone');
        $amount = $request->input('amount');
        $result['success'] = false;

        do {
            if (!$dealID) {
                $result['message'] = 'Не передан ID сделки';
                break;
            }
            if (!$type) {
                $result['message'] = 'Не передан тип льготного периода';
                break;
            }
            if (!$phone) {
                $result['message'] = 'Не передан телефон';
                break;
            }
            if (!$amount) {
                $result['message'] = 'Не передан сумма';
                break;
            }

            if ($type == 5) {
                $text = 'Zavtra Posledniy den LGOTNOGO perioda Oplatite do zavtra' . $amount . 'tg po 0% www.i-credit.kz I mojete snova Vzyat KREDIT no uzhe BOLSHE- vash i-Credit';
            } else if ($type == 6) {
                $text = 'Segodnya Posledniy den LGOTNOGO perioda Oplatite do zavtra' . $amount . 'tg po 0% www.i-credit.kz I mojete snova Vzyat KREDIT no uzhe BOLSHE- vash i-Credit';
            }
            DB::beginTransaction();

            $sms = SMS::insertGetId([
                'type' => $type,
                'status' => 100,
                'phone' => $phone,
                'text' => $text,
                'dealID' => $dealID,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
            if (!$sms) {
                DB::rollBack();
                $result['message'] = 'Something wrong';
                break;
            }
            $send = $this->sendSMS($sms, $phone, $text);

            if (!$send) {
                $result['message'] = 'Отправка смс';
                break;
            }
            $result['success'] = true;
            DB::commit();
        } while (false);

        return response()->json($result);
    }

    // стандартный период
    public function standardPeriod(Request $request)
    {
        $amount = $request->input('amount');
        $prolongation = $request->input('prolongation');
        $phone = $request->input('phone');
        $dealID = $request->input('dealID');
        $type = $request->input('type');
        $result['success'] = true;

        do {
            if (!$amount) {
                $result['message'] = 'Не передан сумма';
                break;
            }
            if (!$prolongation) {
                $result['message'] = 'Не передан сумма пролонгации';
                break;
            }
            if (!$phone) {
                $result['message'] = 'Не передан телефон';
                break;
            }
            if (!$dealID) {
                $result['message'] = 'Не передан номер сделки';
                break;
            }
            if (!$type) {
                $result['message'] = 'Не передан тип сообщение';
                break;
            }
            if ($type == 7) {
                $text = 'Cherez 3dnya Srok Pogasheniya Kredita Vi moJete prolongirovat Kredit oplativ' . $prolongation . 'tg iLi zakrit ego Polnostu ' . $amount . 'tg';
            } else if ($type == 8) {
                $text = 'Cherez 2dnya Srok Pogasheniya Kredita Vi moJete prolongirovat Kredit oplativ' . $prolongation . 'tg iLi zakrit ego Polnostu ' . $amount . 'tg';
            } else if ($type == 9) {
                $text = 'ZAVTRA Srok Pogasheniya Kredita Vi moJete prolongirovat Kredit oplativ' . $prolongation . 'tg iLi zakrit ego Polnostu ' . $amount . 'tg';
            } else if ($type == 10) {
                $text = 'SEGODYA Srok Pogasheniya Kredita Vi moJete prolongirovat Kredit oplativ' . $prolongation . 'tg iLi zakrit ego Polnostu' . $amount . 'tg';
            }
            DB::beginTransaction();
            $smsID = SMS::insertGetId([
                'status' => 100,
                'type' => $type,
                'text' => $text,
                'dealID' => $dealID,
                'phone' => $phone,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
            if (!$smsID) {
                DB::rollBack();
                $result['message'] = 'Something went wrong';
                break;
            }
            $send = $this->sendSMS($smsID, $phone, $text);
            if (!$send) {
                break;
            }
            $result['success'] = true;
            DB::commit();

        } while (false);
        return response()->json($result);
    }

    // просрочка софт
    public function softPeriod(Request $request)
    {
        $amount = $request->input('amount');
        $prolongation = $request->input('prolongation');
        $dealID = $request->input('dealID');
        $phone = $request->input('phone');
        $type = $request->input('type');
        $result['success'] = false;
        do {
            if (!$amount) {
                $result['message'] = 'Не передан сумма';
                break;
            }
            if (!$prolongation) {
                $result['message'] = 'Не передан сумма пролонгации';
                break;
            }
            if (!$phone) {
                $result['message'] = 'Не передан телефон';
                break;
            }
            if (!$dealID) {
                $result['message'] = 'Не передан номер сделки';
                break;
            }
            if (!$type) {
                $result['message'] = 'Не передан тип сообщение';
                break;
            }
            if ($type == 11) {
                $text = 'U Vas Prosrichka NO Mi POKA ne Nachislyaem SHTRAF za ' . $prolongation . 'tg Prodlite Srok Kredita oplatite v Qiwi,kassa24 iLi na Site www.i-credit.kz';
            } else if ($type == 12) {
                $text = '2 DEN Prosrochki, Mi POKA ne Nachislyaem SHTRAF za ' . $prolongation . 'tg Prodlite Srok Kredita oplatite v Qiwi,kassa24 iLi na Site www.i-credit.kz';
            } else if ($type == 13) {
                $text = 'Segodnya 3 DEN Prosrochki, Mi POKA ne Nachislyaem SHTRAF za ' . $prolongation . 'tg Prodlite Srok Kredita oplatite v Qiwi,kassa24 iLi na Site www.i-credit.kz';
            } else if ($type == 14) {
                $text = 'Vi Prosrochili svoy Kredit na 4 dnya za ' . $prolongation . ' Prodlite Srok Kredita BEZ Shtrafov I Peni oplatite v Qiwi,kassa24 iLi na Site';
            } else if ($type == 16) {
                $text = 'ZAVTRA mi Peredadim Vas v HARD KOLEKSHEN za ' . $prolongation . 'tg Prodlite Srok Kredita BEZ Shtrafov I Peni oplatite v Qiwi,kassa24 iLi na Site';
            } else if ($type == 17) {
                $text = 'Segodnya mi Peredadim Vas v HARD KOLEKSHEN za ' . $prolongation . ' Prodlite Srok Kredita BEZ Shtrafov I Peni oplatite v Qiwi,kassa24 iLi na Site';
            }

            DB::beginTransaction();

            $smsID = SMS::insertGetId([
                'status' => 100,
                'type' => $type,
                'text' => $text,
                'dealID' => $dealID,
                'phone' => $phone,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            if (!$smsID) {
                DB::rollBack();
                $result['message'] = 'Something went wrong';
                break;
            }
            $send = $this->sendSMS($smsID, $phone, $text);
            if (!$send) {
                break;
            }
            $result['success'] = true;
            DB::commit();

        } while (false);
        return response()->json($result);

    }

    // просрочка хард
    public function hardPeriod(Request $request)
    {
        $amount = $request->input('amount');
        $prolongation = $request->input('prolongation');
        $dealID = $request->input('dealID');
        $phone = $request->input('phone');
        $type = $request->input('type');

        $result['message'] = false;

        do {
            if (!$amount) {
                $result['message'] = 'Не передан сумма';
                break;
            }
            if (!$prolongation) {
                $result['message'] = 'Не передан сумма пролонгации';
                break;
            }
            if (!$phone) {
                $result['message'] = 'Не передан телефон';
                break;
            }
            if (!$dealID) {
                $result['message'] = 'Не передан номер сделки';
                break;
            }
            if (!$type) {
                $result['message'] = 'Не передан тип сообщение';
                break;
            }
            if ($type == 18) {
                $text = 'Vash Dolg Uvelichilsa Vas Peredali KOLLEKTORAM i Nachisslili vse Peni mojete prolongirovat za' . $prolongation . 'tg ili zakrit zaym za ' . $amount . 'tg vy mozhete Oplatit v Qiwi,kassa24 iLi na Site www.';
            }

            DB::beginTransaction();

            $smsID = SMS::insertGetId([
                'status' => 100,
                'type' => $type,
                'text' => $text,
                'dealID' => $dealID,
                'phone' => $phone,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            if (!$smsID) {
                DB::rollBack();
                $result['message'] = 'Something went wrong';
                break;
            }
            $send = $this->sendSMS($smsID, $phone, $text);
            if (!$send) {
                $result['message'] = 'Отправка смс';
                break;
            }
            $result['success'] = true;
            DB::commit();

        } while (false);
        return response()->json($result);
    }

    // пролонгация
    public function prolongationPeriod(Request $request)
    {
        $type = $request->input('type');
        $amount = $request->input('amount');
        $phone = $request->input('phone');
        $prolongation = $request->input('prolongation');
        $dealID = $request->input('dealID');

        $result['message'] = false;

        do {
            if (!$amount) {
                $result['message'] = 'Не передан сумма';
                break;
            }
            if (!$prolongation) {
                $result['message'] = 'Не передан сумма пролонгации';
                break;
            }
            if (!$phone) {
                $result['message'] = 'Не передан телефон';
                break;
            }
            if (!$dealID) {
                $result['message'] = 'Не передан номер сделки';
                break;
            }
            if (!$type) {
                $result['message'] = 'Не передан тип сообщение';
                break;
            }
            if ($type == 24) {
                $text = 'ZAVTRA Srok Pogasheniya Kredita Vi moJete prolongirovat Kredit oplativ ' . $prolongation . 'tg iLi zakrit ego Polnostu ' . $amount . 'tg';
            } else if ($type == 25) {
                $text = 'SEGODYA Srok Pogasheniya Kredita Vi moJete prolongirovat Kredit oplativ ' . $prolongation . 'tg iLi zakrit ego Polnostu' . $amount . 'tg';
            }
            DB::beginTransaction();

            $smsID = SMS::insertGetId([
                'status' => 100,
                'type' => $type,
                'text' => $text,
                'dealID' => $dealID,
                'phone' => $phone,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            if (!$smsID) {
                DB::rollBack();
                $result['message'] = 'Something went wrong';
                break;
            }
            $send = $this->sendSMS($smsID, $phone, $text);
            if (!$send) {
                break;
            }
            $result['success'] = true;
            DB::commit();


        } while (false);
        return response()->json($result);
    }

    //для подписи продление
    public function prolongation(Request $request){
        $url = $request->input('url');
        $dealID = $request->input('dealID');
        $phone = $request->input('phone');
        $result['success'] = false;
        do{
            if (!$url){
                $result['message'] = 'Не передан ссылка';
                break;
            }
            if (!$dealID){
                $result['message'] = 'Не передан номер сделки';
                break;
            }
            if (!$phone){
                $result['message'] = 'Не передан номер телефона';
                break;
            }
            DB::beginTransaction();
            $text = "Dlya prodlenie zayma proydite po ssilke $url";
            $type = 24;
            $smsID = DB::table('sms')->insertGetId([
                'type' => $type,
                'status' => 100,
                'phone' => $phone,
                'text' => $text,
                'dealID' => $dealID,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
            if (!$smsID){
                DB::rollBack();
                $result['message'] = 'Попробуйте позже';
                break;
            }
            $sendSMS = $this->sendSMS($smsID,$phone,$text);
            if (!$sendSMS){
                DB::rollBack();
                $result['message'] = 'Попробуйте позже';
                break;
            }
            $result['success'] = true;
            DB::commit();

        }while(false);

        return response()->json($result);
    }

    //смс о продление займа

    public function sign(Request $request){
        $dealID = $request->input('dealID');
        $phone = $request->input('phone');
        $type = $request->input('type');
        $date = $request->input('date');
        $result['success'] = false;
        do{
            if (!$dealID){
                $result['message'] = 'Не передан номер сделки';
                break;
            }
            if (!$phone){
                $result['message'] = 'Не передан номер телефона';
                break;
            }
            DB::beginTransaction();
            $text = "Ваш займ продлен до $date";
            $smsID = DB::table('sms')->insertGetId([
                'type' => $type,
                'status' => 100,
                'phone' => $phone,
                'text' => $text,
                'dealID' => $dealID,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
            if (!$smsID){
                DB::rollBack();
                $result['message'] = 'Попробуйте позже';
                break;
            }
            $sendSMS = $this->sendSMS($smsID,$phone,$text);
            if (!$sendSMS){
                DB::rollBack();
                $result['message'] = 'Попробуйте позже';
                break;
            }
            $result['success'] = true;
            DB::commit();

        }while(false);

        return response()->json($result);
    }

    // метод отправление смс
    public function sendSMS($smsID, $phone, $text)
    {
        $login = env('SMS_CONSULT_LOGIN');
        $password = env('SMS_CONSULT_PASSWORD');
        $sender = env('SMS_CONSULT_SENDER');


        $http = new Client;
        try {
            $response = $http->get('http://service.sms-consult.kz/get.ashx?', [
                'query' => [
                    'login' => $login,
                    'password' => $password,
                    'id' => $smsID,
                    'type' => 'message',
                    'recipient' => $phone,
                    'sender' => $sender,
                    'text' => $text
                ],
            ]);

            $s = $response->getBody()->getContents();
            if ($s == 'status=100'){
                return true;
            }
            /*$res = $response->getBody()->getContents();
            if ($res == 'status=100' || $res == 'status=101' || $res == 'status=102') {
                echo "yes";
                return true;
            }*/
        } catch (BadResponseException $e) {
            info($e);
            if ($e->getCode() == 400) {
                info('Something went wrong. Bad request' . $phone);
            } elseif ($e->getCode() == 401) {

                info('Something went wrong. Bad request' . $phone);
            }
            return false;

        }
        return false;
    }

    public function checkCron()
    {
        $sql = SMS::where('type', '!=', 1)->where('status', '!=', 102)->get();
        foreach ($sql as $s) {
            $http = new Client();
            try {
                $response = $http->get('http://service.sms-consult.kz/get.ashx?', [
                    'query' => [
                        'login' => env('SMS_CONSULT_LOGIN'),
                        'password' => env('SMS_CONSULT_PASSWORD'),
                        'id' => $s->id,
                        'type' => 'status',
                    ],
                ]);
                $result = $response->getBody()->getContents();

                if ($result == 'status=100') {
                    $update = SMS::where('id', $s->id)->update(['status' => 100, 'updated_at' => Carbon::now()]);
                    if (isset($s) && isset($s->dealID)) {
                        $status = DB::table('status')->where('dealID', $s->dealID)
                            ->where('status', 100)
                            ->where('sms_id', $s->id)
                            ->first();
                        if (!isset($status)) {
                            $statusInsert = DB::table('status')->insertGetId([
                                'status' => 100,
                                'dealID' => $s->dealID,
                                'sms_id' => $s->id,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now(),
                            ]);
                            $url = "https://icredit-crm.kz/api/webhock/responseDeal.php?dealID=$s->dealID&status=100";
                        }

                    } else if (isset($s) && isset($s->leadID)) {
                        $status = DB::table('status')->where('leadID', $s->leadID)
                            ->where('status', 100)
                            ->where('sms_id', $s->id)
                            ->first();
                        if (!isset($status)) {
                            $statusInsert = DB::table('status')->insertGetId([
                                'status' => 100,
                                'leadID' => $s->leadID,
                                'sms_id' => $s->id,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now(),
                            ]);
                            $url = "https://icredit-crm.kz/api/webhock/responseLead.php?leadID=$s->leadID&status=100";
                        }
                    }
                    if (isset($url)) {

                        file_get_contents($url);
                    }

                }

                if ($result == 'status=101') {
                    $update = SMS::where('id', $s->id)->update(['status' => 101, 'updated_at' => Carbon::now()]);

                    if (isset($s) && isset($s->dealID)) {
                        $status = DB::table('status')->where('dealID', $s->dealID)
                            ->where('status', 101)
                            ->where('sms_id', $s->id)
                            ->first();
                        if (!isset($status)) {
                            $statusInsert = DB::table('status')->insertGetId([
                                'status' => 101,
                                'dealID' => $s->dealID,
                                'sms_id' => $s->id,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now(),
                            ]);
                            $url = "https://icredit-crm.kz/api/webhock/responseDeal.php?dealID=$s->dealID&status=101";
                        }
                    } else if (isset($s) && isset($s->leadID)) {
                        $status = DB::table('status')->where('leadID', $s->leadID)
                            ->where('status', 101)
                            ->where('sms_id', $s->id)
                            ->first();
                        if (!isset($status)) {
                            $statusInsert = DB::table('status')->insertGetId([
                                'status' => 101,
                                'leadID' => $s->leadID,
                                'sms_id' => $s->id,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now(),
                            ]);
                            $url = "https://icredit-crm.kz/api/webhock/responseLead.php?leadID=$s->leadID&status=101";
                        }
                    }

                    if (isset($url)) {
                        file_get_contents($url);
                    }
                }

                if ($result == 'status=102') {
                    $update = SMS::where('id', $s->id)->update(['status' => 102, 'updated_at' => Carbon::now()]);
                    if (isset($s) && isset($s->dealID)) {
                        $status = DB::table('status')->where('dealID', $s->dealID)
                            ->where('status', 102)
                            ->where('sms_id', $s->id)
                            ->first();
                        if (!isset($status)) {
                            $statusInsert = DB::table('status')->insertGetId([
                                'status' => 102,
                                'dealID' => $s->dealID,
                                'sms_id' => $s->id,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now(),
                            ]);
                            $url = "https://icredit-crm.kz/api/webhock/responseDeal.php?dealID=$s->dealID&status=102";
                        }
                    } else if (isset($s) && isset($s->leadID)) {
                        $status = DB::table('status')->where('leadID', $s->leadID)
                            ->where('status', 102)
                            ->where('sms_id', $s->id)
                            ->first();
                        if (!isset($status)) {
                            $statusInsert = DB::table('status')->insertGetId([
                                'status' => 102,
                                'leadID' => $s->leadID,
                                'sms_id' => $s->id,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now(),
                            ]);
                            $url = "https://icredit-crm.kz/api/webhock/responseLead.php?leadID=$s->leadID&status=102";
                        }
                    }

                    if (isset($url)) {
                        file_get_contents($url);
                    }
                }
            } catch (BadResponseException $e) {
                info($e);
            }
        }
    }

    //Ваш займ погашен
    public function repayment(Request $request)
    {
        $phone = $request->input('phone');
        $dealID = $request->input('dealID');
        $smsType = $request->input('smsType');
        $text = 'Ваш кредит полностью ПОГАШЕН- Спасибо что выбрали НАС';
        $result['success'] = false;

        do {
            if (!$phone) {
                $result['message'] = 'Не передан номер телефона';
                break;
            }
            if (!$dealID) {
                $result['message'] = 'Не передан номер сделки';
                break;
            }
            if (!$smsType) {
                $result['message'] = 'Не передан тип сообщение';
                break;
            }
            DB::beginTransaction();

            $smsID = SMS::insertGetId([
                'type' => $smsType,
                'text' => $text,
                'phone' => $phone,
                'status' => 100,
                'dealID' => $dealID,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            if (!$smsID) {
                DB::rollBack();
                $result['message'] = 'Something went wrong!';
                break;
            }
            $send = $this->sendSMS($smsID, $phone, $text);
            if ($send == false) {
                break;
            }
            $result['success'] = true;

            DB::commit();
        } while (false);
        return response()->json($result);
    }

    //Рекламный
    public function advertisement(Request $request)
    {
        $dealID = $request->input('dealID');
        $phone = $request->input('phone');
        $type = $request->input('type');
        $amount = $request->input('amount');
        $result['success'] = false;
        do {
            if (!$dealID) {
                $result['message'] = 'Не передан номер сделки';
                break;
            }
            if (!$phone) {
                $result['message'] = 'Не передан номер телефона';
                break;
            }
            if (!$type) {
                $result['message'] = 'Не передан тип сообщение';
                break;
            }

            if (!$amount) {
                $result['message'] = 'Не передан сумма';
                break;
            }
            $text = "ВАМ Одобрен Кредит под 0% с УВЕЛИЧЕННОЙ суммой $amount Ваш I-Credit";
            DB::beginTransaction();

            $smsID = SMS::insertGetId([
                'status' => 100,
                'type' => $type,
                'text' => $text,
                'dealID' => $dealID,
                'phone' => $phone,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            if (!$smsID) {
                DB::rollBack();
                $result['message'] = 'Something went wrong';
                break;
            }
            $send = $this->sendSMS($smsID, $phone, $text);
            if (!$send) {
                break;
            }
            $result['success'] = true;
            DB::commit();
        } while (false);
        return response()->json($result);
    }

    public function index(Request $request)
    {
        $token = $request->input('token');
        $user = User::where('remember_token', $token)->first();

        $sms = DB::table('sms')
            ->join('sms_statuses', 'sms.status', '=', 'sms_statuses.status')
            ->join('sms_types', 'sms.type', '=', 'sms_types.id')
            ->select('sms.text', 'sms_types.name as type', 'sms.phone', 'sms_statuses.name as status', 'sms.status as status_id', 'sms.created_at')->orderBy('created_at', 'desc')->paginate(15);
        if ($token && $user) {
            return response()->json($sms);
        }
        $result['success'] = false;
        $result['message'] = 'Не передан токен или пользователь не найден';
        return response()->json($result);
    }

    public function getSmsTypes(Request $request)
    {
        $sms_types = DB::table('sms_types')->get();
        return response()->json($sms_types);
    }

    public function getSmsStatuses(Request $request)
    {
        $sms_types = DB::table('sms_statuses')->get();
        return response()->json($sms_types);
    }

    public function indexFile(Request $request)
    {
        $token = $request->input('token');
        $user = User::where('remember_token', $token)->first();
        $phone = $request->input('phone');
        $sms_type = $request->input('sms_type');
        $date_from = $request->input('date_from');
        $date_to = $request->input('date_to');


        $sms = DB::table('sms')->orderBy('updated_at', 'desc');
        if ($phone != '') {
            $sms = $sms->where('phone', $phone);
        }
        if ($sms_type != '') {
            $sms = $sms->where('type', $sms_type);
        }
        if ($date_from != '' && $date_to == '') {
            $sms = $sms->whereBetween('created_at', [$date_from, Carbon::now()]);
        }
        if ($date_from == '' && $date_to != '') {
            $sms = $sms->whereBetween('created_at', [$date_from === '2000-01-01', $date_to]);
        }
        if ($date_from != '' && $date_to != '') {
            $sms = $sms->whereBetween('created_at', [$date_from, $date_to]);
        }
        $sms = $sms->get();

        $types = DB::table('sms_types')->get();
        $statuses = DB::table('sms_statuses')->get();
        $smsStatuses = [];
        foreach ($statuses as $status) {
            $smsStatuses[$status->status] = $status->name;
        }
        $typesData = [];
        foreach ($types as $type) {
            $typesData[$type->id] = $type->name;
        };
        $data = [];
        $index = 0;
        foreach ($sms as $s) {
            $data[$index] = [
                'Номер' => $s->phone,
                'Тип сообщений' => $typesData[$s->type],
                'Текст' => $s->text,
                'Статус сообщений' => $smsStatuses[$s->status],
                'Изменено' => date('d.m.Y H:i:s', strtotime($s->updated_at)),
            ];
            $index++;
        }

        if ($token && $user) {
            return response()->json($data);
        }

        $result['success'] = false;
        $result['message'] = 'Не передан токен или пользователь не найден';

        return response()->json($result);

    }

    public function filter(Request $request)
    {
        $token = $request->input('token');
        $phone = $request->input('phone');
        $user = User::where('remember_token', $token)->first();
        $date_from = $request->input('date_from');
        $date_to = $request->input('date_to');
        $sms_type = $request->input('sms_type');
//->join('sms_statuses', 'sms.status', '=', 'sms_statuses.status')
        $status = [];
        $type = [];
        $statusTable = DB::table('sms_statuses')->select('status', 'name')->get();
        $typeTable = DB::table('sms_types')->select('id', 'name')->get();
        foreach ($statusTable as $s) {
            $status[$s->status] = $s->name;
        }
        foreach ($typeTable as $t) {
            $type[$t->id] = $t->name;
        }
//'sms_statuses.name as status',
        //->join('sms_types', 'sms.type', '=', 'sms_types.id')
        $sms = DB::table('sms')
            ->select('sms.text', 'sms.type', 'sms.type as sms_type', 'sms.phone', 'sms.status','sms.status as status_id', 'sms.created_at')
            ->orderBy('created_at', 'desc');
        if ($token && $user) {
            if ($phone != '') {
                $sms = $sms->where('sms.phone', $phone);
            }
            if ($sms_type != '') {
                $sms = $sms->where('sms.type', $sms_type);
            }
            if ($date_from != '' && $date_to == '') {
                $sms = $sms->whereBetween('sms.created_at', [$date_from, Carbon::now()]);
            }
            if ($date_from == '' && $date_to != '') {
                $sms = $sms->whereBetween('sms.created_at', [$date_from === '2000-01-01', $date_to]);
            }
            if ($date_from != '' && $date_to != '') {
                $sms = $sms->whereBetween('sms.created_at', [$date_from, $date_to]);
            }

            $sms = $sms->paginate(15)->appends($request->all());
            foreach ($sms as $sm) {
                $sm->status = $status[$sm->status];
                $sm->type = $type[$sm->type];
            }



            return response()->json($sms);
        }


        $result['success'] = false;
        $result['message'] = 'Не передан токен телефон или пользователь не найден';
        return response()->json($result);
    }
}
