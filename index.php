<?php

// Composerでインストールしたライブラリを一括読み込み
require_once __DIR__ . '/vendor/autoload.php';

// dateの基準を東京にする
date_default_timezone_set('Asia/Tokyo'); 

// テーブル定義
define('M_USER', 'm_line_user_data');
define('T_TIME', 't_line_time_card');

// アクセストークンを使いCurlHTTPClientをインスタンス化
$httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));

// CurlHTTPClientとシークレットを使いLINEBotをインスタンス化
$bot = new \LINE\LINEBot($httpClient, ['channelSecret' => getenv('CHANNEL_SECRET')]);

// LINE Messaging APIがリクエストに付与した署名を取得
$signature = $_SERVER['HTTP_' . \LINE\LINEBot\Constant\HTTPHeader::LINE_SIGNATURE];

// 署名が正当化チェック。正当であればリクエストをパースし配列へ
// 不正であれば例外の内容を出力
try {
	$events = $bot->parseEventRequest(file_get_contents('php://input'), $signature);
} catch(\LINE\LINEBot\Exception\InvalidSignatureException $e) {
	error_log('parseEventRequest failed. InvalidSignatureException => '.var_export($e, true));
} catch(\LINE\LINEBot\Exception\UnknownEventTypeException $e) {
	error_log('parseEventRequest failed. UnknownEventTypeException => '.var_export($e, true));
} catch(\LINE\LINEBot\Exception\UnknownMessageTypeException $e) {
	error_log('parseEventRequest failed. UnknownMessageTypeException => '.var_export($e, true));
} catch(\LINE\LINEBot\Exception\InvalidEventRequestException $e) {
	error_log('parseEventRequest failed. InvalidEventRequestException => '.var_export($e, true));
}
// 配列に格納された各イベントをループで処理
foreach ($events as $event) {
	
	// TextMessageクラスのインスタンスであれば内容を出力
	if ($event instanceof \LINE\LINEBot\Event\PostbackEvent) {
		// テキストを返信する
		//replyTextMessage($bot, $event->getReplyToken(), 'Postback受信「' . $event->getPostbackData() . '」');
		
		$postbackData = $event->getPostbackData();
		
		$arrData = explode(',', $postbackData);
		
		// 時間を切り上げる
		//$nowEditTime = ceilPerTime(strtotime(date("H:i:s")), 15);
		$nowEditTime = date("H:i:s");
		
		if (strpos($arrData[0], 'A') !== false) {
			// 出勤情報の存在をチェックする
			$timeSrg = getAttendTimeData($arrData[1]);
			
			// 出勤情報が存在しない場合、出勤情報を登録する
			if ($timeSrg === PDO::PARAM_NULL) {
				registerAttendTime($arrData[1], $nowEditTime);
				replyTextMessage($bot, $event->getReplyToken(), '出勤登録しました。' . date("H:i:s"));

				// 上長のユーザを取得
				$data = getUserCd($arrData[1]);
				
				// 出勤メッセージを加工
				$message = getMessage($arrData[1], '出勤');

				// 所属する上長へメッセージを送信(複数いる場合は取得したユーザ情報分送信する)
				foreach($data as $value){
					$userId = $value;
					//$userDesu = 'Uc64bc90653c1720b782cfd704c515833';

					$response = $bot->pushMessage($userId,
					    new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($message));
					//$response = $bot->pushMessage($userDesu, new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($userId));

					if (!$response->isSucceeded()) {
						error_log('Failed!' . $response->getHTTPStatus . ' ' . $response->getRawBody());
					}
				}
			}
			else {
				// 出勤した時間を返却する
				$attendTime = getAttendTimeAr($timeSrg);
				replyTextMessage($bot, $event->getReplyToken(), $attendTime . 'に出勤済みです。');
			}
		}
		else if (strpos($arrData[0], 'B') !== false) {
			// 一旦現在時間を出す
			//$csvMessage = createCSV();
			
			replyTextMessage($bot, $event->getReplyToken(), $arrData[1]);
		}
		else if (strpos($arrData[0], 'C') !== false) {
			// 出勤情報の存在をチェックする
			$timeSrg = getAttendTimeData($arrData[1]);
			
			// 出勤情報が存在しない場合、出勤情報を登録する
			if ($timeSrg === PDO::PARAM_NULL) {
				replyTextMessage($bot, $event->getReplyToken(), '出勤されていません。「出勤」と入力して出勤してください。');
			}
			else {
			
				// 退勤時間の存在をチェックする
				$leaveTimeSrg = getLeaveTimeData($arrData[1]);
				
				if ($leaveTimeSrg === PDO::PARAM_NULL) {
					// 出勤済みかつ退勤していない場合は退勤時間を登録する
					registerLeaveTime($arrData[1], $timeSrg, $nowEditTime);
					replyTextMessage($bot, $event->getReplyToken(), '退勤登録しました。' . date("H:i:s"));

					$data = getUserCd($arrData[1]);
					//$userDesu = 'Uc64bc90653c1720b782cfd704c515833';
					
					$message = getMessage($arrData[1], '退勤');
					
					foreach($data as $value){
						$userId = $value;
						
						$response = $bot->pushMessage($userId,
						    new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($message));

						if (!$response->isSucceeded()) {
							error_log('Failed!' . $response->getHTTPStatus . ' ' . $response->getRawBody());
						}
					}
				}
				else {
					// 退勤した時間を返却する
					$attendTime = getLeaveTimeAr($timeSrg);
					replyTextMessage($bot, $event->getReplyToken(), $attendTime . 'に退勤済みです。');
				}
			}
		}
		else if (strpos($arrData[0], 'D') !== false) {
			// 一旦現在時間を出す
			replyTextMessage($bot, $event->getReplyToken(), date("Y/m/d H:i:s") . '!!!!!!??');
		}
		continue;
	}
	
	// MessageEventクラスのインスタンスでなければ処理をスキップ
	if (!($event instanceof \LINE\LINEBot\Event\MessageEvent)) {
		error_log('Non Message event has come');
		continue;
	}
	
	// TextMessageクラスのインスタンスでなければ処理をスキップ
	if (!($event instanceof \LINE\LINEBot\Event\MessageEvent\TextMessage)) {
		error_log('Non Text Message has come');
		continue;
	}
	
	// 変数定義
	// 入力されたテキストを格納
	$textData = $event->getText();
	
	//-- 出勤フラグ
	$attendFlg = False;
	//-- 退勤フラグ
	$leaveFlg = False;
	//-- ユーザID
	$userId = $event->getUserId();
	//-- Profile取得
	$profile = $bot->getProfile($event->getUserId())->getJSONDecodedBody();
	
	//-- ユーザ名(自分のプロフィールの名前)を取得
	$userName = $profile['displayName'];
	
	//$bot->replyText($event->getReplyToken(), $userName);
	//$bot->replyText($event->getReplyToken(), $profile['pictureUrl']);
	
	// 出退勤かどうかを判定
	if (strpos($textData, '出退勤') !== false) {
		// 出退勤の場合は何もしない
	}
	else {
		if (strpos($textData, '出勤') !== false) {
			$attendFlg = True;
		}
		if (strpos($textData, '退勤') !== false) {
			$leaveFlg = True;
		}
	}
		
	$userSrg = getUserData($userId);
		
	// DBにユーザ情報が存在しない場合、ユーザ情報を登録する
	if ($userSrg === PDO::PARAM_NULL) {
		registerUser($userId, $userName);
		$bot->replyText($event->getReplyToken(), "ユーザデータが存在しないため、登録しました。再度入力してください。");
	}
	
	if ($attendFlg && $leaveFlg) {
		// 両方がテキストにある場合は再入力を表示
		$bot->replyText($event->getReplyToken(), "出勤、退勤が混在しているので再入力してください。");
	}
	else if ($attendFlg) {
		// 出勤の場合
		//$bot->replyText($event->getReplyToken(), "出勤！！！");
		$urlCSV = "https://test-app-csv-mari-magno.herokuapp.com/?page=";

		$urlCSV = $urlCSV . (string)$userId;

		replyButtonsTemplate(
			$bot,
			$event->getReplyToken(),
			$userName . 'さん',
			'https://' . $_SERVER['HTTP_HOST'] . '/imgs/marimagno_logo.jpg',
			$userName . 'さん',
			'出勤しますか？',
			new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder ('出勤する', 'A,' . $userSrg),
			// 変更は工事中
			//new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder ('出勤時間を変更する', 'B,' . $userSrg),
			new \LINE\LINEBot\TemplateActionBuilder\UriTemplateActionBuilder ('Webで見る', $urlCSV),
			new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder ('CSV出力', 'B,' . $urlCSV)
		);

	}
	else if ($leaveFlg) {
		// 退勤の場合
		//$bot->replyText($event->getReplyToken(), "退勤！！！");
		replyButtonsTemplate(
			$bot,
			$event->getReplyToken(),
			$userName . 'さん',
			'https://' . $_SERVER['HTTP_HOST'] . '/imgs/marimagno_logo.jpg',
			$userName . 'さん',
			'退勤しますか？',
			//new \LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder ('退勤する', 'テスト
			new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder ('退勤する', 'C,' . $userSrg),
			// 変更は工事中
			//new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder ('退勤時間を変更する', 'D' . $userSrg),
			new \LINE\LINEBot\TemplateActionBuilder\UriTemplateActionBuilder ('Webで見る', 'http://google.jp')
		);
	}
	
	
	//replyTextMessage($bot, $event->getReplyToken(), 'TextMessage');
}

// DBからユーザ情報を取得
function getUserData($userId) {
	$dbh = dbConnection::getConnection();
	$sql = 'select * from ' . M_USER . ' where ? = pgp_sym_decrypt(user_secret_id, \'' . getenv('DB_ENCRYPT_PASS') . '\')';
	//$sql = 'select * from ' . M_USER . ' where user_id = ?';
	$sth = $dbh->prepare($sql);
	$sth->execute(array($userId));
	
	// データが存在しない場合はNULL
	if (!($row = $sth->fetch())) {
		return PDO::PARAM_NULL;
	}
	else {
		return json_decode($row['user_srg']);
	}
}

// ユーザ情報を登録する
function registerUser($userId, $userName) {
	$dbh = dbConnection::getConnection();

	$sql = 'insert into ' . M_USER . ' (user_id, user_secret_id, user_name) values (?, pgp_sym_encrypt(?, \'' . getenv('DB_ENCRYPT_PASS') . '\'), ?) ';
	$sth = $dbh->prepare($sql);
	$sth->execute(array($userId, $userId, $userName));
}

// 出勤情報確認SQL
function getAttendTimeData($userSrg) {
	$dbh = dbConnection::getConnection();
	$sql = 'select * from ' . T_TIME . ' where user_srg = ? and stamp_date = ?';
	$sth = $dbh->prepare($sql);
	$sth->execute(array($userSrg, date("Y/m/d")));
	
	// データが存在しない場合はNULL
	if (!($row = $sth->fetch())) {
		return PDO::PARAM_NULL;
	}
	else {
		return json_decode($row['time_card_srg']);
	}
}

// 出勤情報登録SQL
function registerAttendTime($userSrg, $editTime) {
	$dbh = dbConnection::getConnection();
	
	$sql = 'insert into ' . T_TIME . ' (user_srg, stamp_date, attend_time, attend_edit_time) values (?, ?, ?, ?)';
	$sth = $dbh->prepare($sql);
	$sth->execute(array($userSrg, date("Y/m/d"), date("H:i:s"), $editTime));
}

// 出勤時間取得SQL
function getAttendTimeAr($timeSrg) {
	$dbh = dbConnection::getConnection();
	
	$sql = 'select to_char(attend_time, \'HH24:MI:SS\') as atime from ' . T_TIME . ' where time_card_srg = ?';
	$sth = $dbh->prepare($sql);
	$sth->execute(array($timeSrg));
	
	// データが存在しない場合はNULL
	if (!($row = $sth->fetch())) {
		return PDO::PARAM_NULL;
	}
	else {
		$result = $row['atime'];
		return $result;
	}
}

// 退勤情報確認SQL
function getLeaveTimeData($userSrg) {
	$dbh = dbConnection::getConnection();
	$sql = 'select * from ' . T_TIME . ' where user_srg = ? and stamp_date = ? and leave_time is not null';
	$sth = $dbh->prepare($sql);
	$sth->execute(array($userSrg, date("Y/m/d")));
	
	// データが存在しない場合はNULL
	if (!($row = $sth->fetch())) {
		return PDO::PARAM_NULL;
	}
	else {
		return json_decode($row['time_card_srg']);
	}
}

// 退勤情報登録SQL
function registerLeaveTime($userSrg, $timeSrg, $editTime) {
	$dbh = dbConnection::getConnection();
	
	$sql = 'update ' . T_TIME . ' set leave_time = ?, leave_edit_time = ? where user_srg = ? and time_card_srg  = ?';
	$sth = $dbh->prepare($sql);
	$sth->execute(array(date("H:i:s"), $editTime, $userSrg, $timeSrg));
}

// 退勤時間取得SQL
function getLeaveTimeAr($timeSrg) {
	$dbh = dbConnection::getConnection();
	
	$sql = 'select to_char(leave_time, \'HH24:MI:SS\') as ltime from ' . T_TIME . ' where time_card_srg  = ?';
	$sth = $dbh->prepare($sql);
	$sth->execute(array($timeSrg));
	
	// データが存在しない場合はNULL
	if (!($row = $sth->fetch())) {
		return PDO::PARAM_NULL;
	}
	else {
		$result = $row['ltime'];
		return $result;
	}
}

// メッセージ加工
function getMessage($userSrg, $word) {
	$name = '';
	$timeSrg = 0;
	$timeW = '';
	$rMessage = '';
	
	$dbh = dbConnection::getConnection();
	$sql = 'select another_user_name from ' . M_USER . ' where user_srg = ?';
	$sth = $dbh->prepare($sql);
	$sth->execute(array($userSrg));
	
	// データが存在しない場合は何もしないL
	if (!($row = $sth->fetch())) {
	}
	else {
		$name = $row['another_user_name'];
	}
	
	if (strpos($word, '出勤') !== false) {
		$timeSrg = getAttendTimeData($userSrg);
		$timeW = getAttendTimeAr($timeSrg);
	}
	else if (strpos($word, '退勤') !== false){
		$timeSrg = getLeaveTimeData($userSrg);
		$timeW = getLeaveTimeAr($timeSrg);
	}
	
	$rMessage = $name . 'さんが' . $word . 'しました@' . $timeW;
	
	return $rMessage;
}

// ユーザコードを取得
function getUserCd($userSrg) {
	$dbh = dbConnection::getConnection();
	$sql = 'select user_id from m_line_user_data a ,( select user_srg, master_cd, slave_cd from m_line_user_data where user_srg = ? ) b where ( a.master_cd <= b.master_cd or a.master_cd = 0 ) and a.master_cd <= b.slave_cd and a.user_srg <> b.user_srg ';
	$sth = $dbh->prepare($sql);
	$sth->execute(array($userSrg));
	
	$data = array();
	foreach ($sth as $row) {
		array_push($data, $row['user_id']);
	}
	return $data;
}

// CSV出力
function createCSV() {
	$message = [];
	$data = [
	    ['ID', '名前', '年齢'],
	    ['1', '田中', '30'],
	    ['2', '小林', '26'],
	    ['3', '江口', '32']
	];

	$file = new SplFileObject('member.csv', 'w');
 
	foreach ($data as $line) {
		$file->fputcsv($line);
	}
	
	$message[] = [
		'type' => 'file',
		'fileName' => $file
	];
	return $message;
}

// POSTメソッドで渡される値を取得、表示
//$inputString = file_get_contents('php://input');
//error_log($inputString)

//--------------------------------------------------------------------------------------------------
//-- 
//-- 汎用的に使えるFunctionたち
//-- 
//--------------------------------------------------------------------------------------------------

// テキストを返信。引数はLINEBot、返信先、テキスト
function replyTextMessage($bot, $replyToken, $text) {

	// TextMessageBuilderの引数はテキスト
	$response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($text));
	
	// レスポンスが異常な場合
	if (!$response->isSucceeded()) {
		error_log('Failed! '. $response->getHTTPStatus . ' '. $response->getRawBody());
	}
}

// ファイルを返信。引数はLINEBot、返信先、ファイルメッセージ
function replyFileMessage($bot, $replyToken, $fileMessage) {

	// TextMessageBuilderの引数はテキスト
	$response = $bot->replyMessage($replyToken, $fileMessage);
	
	// レスポンスが異常な場合
	if (!$response->isSucceeded()) {
		error_log('Failed! '. $response->getHTTPStatus . ' '. $response->getRawBody());
	}
}

// 画像を返信。引数はLINEBot、返信先、画像URL、サムネイルURL
function replyImageMessage($bot, $replyToken, $originalUmageUrl, $previewImageUrl) {

	// ImageMessageBuilderの引数は画像URL、サムネイルURL
	$response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\ImageMessageBuilder($originalUmageUrl, $previewImageUrl));
	
		// レスポンスが異常な場合
	if (!$response->isSucceeded()) {
		error_log('Failed! '. $response->getHTTPStatus . ' '. $response->getRawBody());
	}
}

// 位置情報を返信。引数はLINEBot、返信先、タイトル、住所、緯度、経度
function replyLocationMessage($bot, $replyToken, $title, $address, $lat, $lon) {

	// LocationMessageBuilderの引数はタイトル、住所、緯度、経度
	$response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\LocationMessageBuilder($title, $address, $lat, $lon));
	
		// レスポンスが異常な場合
	if (!$response->isSucceeded()) {
		error_log('Failed! '. $response->getHTTPStatus . ' '. $response->getRawBody());
	}
}

// スタンプを返信。引数はLINEBot、返信先、スタンプのパッケージID、スタンプID
function replyStickerMessage($bot, $replyToken, $packageId, $stickerId) {

	// StickerMessageBuilderの引数はスタンプのパッケージID、スタンプID
	$response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\StickerMessageBuilder($packageId, $stickerId));
	
	// レスポンスが異常な場合
	if (!$response->isSucceeded()) {
		error_log('Failed! '. $response->getHTTPStatus . ' '. $response->getRawBody());
	}
}

// 動画を返信。引数はLINEBot、返信先、動画URL、サムネイルURL
function replyVideoMessage($bot, $replyToken, $originalContentUrl, $previewImageUrl) {

	// VideoMessageBuilderの引数は動画URL、サムネイルURL
	$response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\VideoMessageBuilder($originalContentUrl, $previewImageUrl));
	
		// レスポンスが異常な場合
	if (!$response->isSucceeded()) {
		error_log('Failed! '. $response->getHTTPStatus . ' '. $response->getRawBody());
	}
}

// オーディオファイルを返信。引数はLINEBot、返信先、ファイルのURL、ファイルの再生時間
function replyAudioMessage($bot, $replyToken, $originalContentUrl, $audioLength) {

	// AudioMessageBuilderの引数はファイルのURL、ファイルの再生時間
	$response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\AudioMessageBuilder($originalContentUrl, $audioLength));
	
		// レスポンスが異常な場合
	if (!$response->isSucceeded()) {
		error_log('Failed! '. $response->getHTTPStatus . ' '. $response->getRawBody());
	}
}

// 複数のメッセージをまとめて返信。引数はLINEBot、返信先、メッセージ(可変長引数)
function replyMultiMessage($bot, $replyToken, ...$msgs) {

	// MultiMessageBuilderをインスタンス化
	$bulider = new \LINE\LINEBot\MessageBuilder\MultiMessageBuilder();
	
	// ビルダーにメッセージをすべて追加
	foreach($msgs as $value) {
		$bulider->add($value);
	}
	$response = $bot->replyMessage($replyToken, $bulider);
	
	// レスポンスが異常な場合
	if (!$response->isSucceeded()) {
		error_log('Failed! '. $response->getHTTPStatus . ' '. $response->getRawBody());
	}
}

// Buttonsテンプレートを返信。引数はLINEBot、返信先、代替テキスト、画像URL、タイトル、本文、アクション(可変長引数)
function replyButtonsTemplate($bot, $replyToken, $alternativeText, $imageUrl, $title, $text, ...$actions) {
	
	// アクションを格納する配列
	$actionArray = array();
	
	//アクションをすべて追加
	foreach($actions as $value) {
		array_push($actionArray, $value);
	}
	
	// ButtonTemplateBuilderをインスタンス化
	// 引数はタイトル、本文、画像URL、アクションの配列
	//$buttonTemplateBulider = new \LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder($title, $text, $imageUrl, $actionArray);
	
	// TemplateMessageBuilderの引数は代替テキスト、ButtonTemplateBuilder
	$bulider = new \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder(
		$alternativeText,
		new \LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder($title, $text, $imageUrl, $actionArray));
	
	$response = $bot->replyMessage($replyToken, $bulider);
	
	// レスポンスが異常な場合
	if (!$response->isSucceeded()) {
		error_log('Failed! '. $response->getHTTPStatus . ' '. $response->getRawBody());
	}
}

class dbConnection {
	// インスタンス
	protected static $db;
	
	// コンストラクタ
	private function __construct() {
		try {
			// 環境変数からデータベースへの接続情報を取得
			$url = parse_url(getenv('DATABASE_URL'));
			
			// データソース
			$dsn = sprintf('pgsql:host=%s;dbname=%s', $url['host'], substr($url['path'], 1));
			
			// 接続を確立
			self::$db = new PDO($dsn, $url['user'], $url['pass']);
			
			// エラー時、例外をスロー
			self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}
		catch (PDOException $e){
			echo 'Connection Error: ' . $e->getMessage();
		}
	}
	
	// シングルトン
	// 存在しない場合のみインスタンス化
	public static function getConnection() {
		if (!self::$db) {
			new dbConnection();
		}
		
		return self::$db;
	}
}

/**
 * 時間(hhmm)を指定した分単位で切り上げる
 * 
 * @param $time 時間と分の文字列(1130, 11:30など)
 * @param $per 切り上げる単位(分) 5分なら5
 * @return false or 切り上げられた DateTime オブジェクト(->fomat で自由にフォーマットして使用する)
 */
function ceilPerTime($time, $per){

    // 値がない時、単位が0の時は false を返して終了する
    if( !isset($time) || !is_numeric($per) || ($per == 0 )) {
        return false;
    }else{
        $deteObj = new DateTime($time);
        // 指定された単位で切り上げる
        // フォーマット文字 i だと、 例えば1分が 2桁の 01 となる(1桁は無い）ので、整数に変換してから切り上げる
        $ceil_num = ceil(sprintf('%d', $deteObj->format('i'))/$per) *$per;

        // 切り上げた「分」が60になったら「時間」を1つ繰り上げる
        // 60分 -> 00分に直す
        $hour = $deteObj->format('H');

        if( $ceil_num == 60 ) {
            $hour = $deteObj->modify('+1 hour')->format('H');
            $ceil_num = '00';
        }
        $have = $hour.sprintf( '%02d', $ceil_num );

        return new DateTime($have);
    }
}

/**
 * 時間(hhmm)を指定した分単位で切り捨てる
 * 
 * @param $time 時間と分の文字列(1130, 11:30など)
 * @param $per 切り捨てる単位(分) 5分なら5
 * @return false or 切り捨てられた DateTime オブジェクト(->fomat で自由にフォーマットして使用する)
 */
function floorPerTime($time, $per){

    // 値がない時、単位が0の時は false を返して終了する
    if( !isset($time) || !is_numeric($per) || ($per == 0 )) {
        return false;
    }else{
        $deteObj = new DateTime($time);

        // 指定された単位で切り捨てる
        // フォーマット文字 i だと、 例えば1分が 2桁の 01 となる(1桁は無い）ので、整数に変換してから切り捨てる
        $ceil_num = floor(sprintf('%d', $deteObj->format('i'))/$per) *$per;

        $hour = $deteObj->format('H');

        $have = $hour.sprintf( '%02d', $ceil_num );

        return new DateTime($have);
    }
}

?>